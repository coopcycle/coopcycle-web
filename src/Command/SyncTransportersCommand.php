<?php

namespace AppBundle\Command;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Edifact\EDIFACTMessageRepository;
use AppBundle\Entity\Package;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\CalculateUsingPricingRules;
use AppBundle\Service\DeliveryOrderManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Transporter\ImportFromPoint;
use AppBundle\Transporter\ReportFromCC;
use AppBundle\Transporter\TransporterHelpers;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Transporter\DTO\Mesurement;
use Transporter\DTO\Point;
use Transporter\Enum\INOVERTMessageType;
use Transporter\Enum\NameAndAddressType;
use Transporter\Enum\TransporterName;
use Transporter\Interface\TransporterSync;
use Transporter\Transporter;
use Transporter\TransporterException;
use Transporter\TransporterImpl;
use Transporter\TransporterOptions;
use Transporter\TransporterSyncOptions;

class SyncTransportersCommand extends Command {

    use LockableTrait;

    private string $transporter;
    private Address $HQAddress;
    private Store $store;

    private TransporterImpl $impl;

    private ?string $companyLegalName;
    private ?string $companyLegalID;

    private bool $dryRun;
    private OutputInterface $output;

    public function __construct(
        private string $appName,
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $params,
        private SettingsManager $settingsManager,
        private LoggerInterface $transporterLogger,
        private ImportFromPoint $importFromPoint,
        private ReportFromCC $reportFromCC,
        private Filesystem $edifactFs,
        private DeliveryOrderManager $deliveryOrderManager,
    )
    { parent::__construct(); }

    protected function configure(): void
    {
        $this->setName('coopcycle:transporters:sync')
        ->setDescription('Synchronizes transporters');

        $this->addArgument('transporter', InputArgument::REQUIRED, 'Transporter name');

        $this->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Dry run mode');
    }

    /**
     * @throws Exception
     */
    private function setup(TransporterName $transporter): void
    {
        $pos = explode(',', $this->settingsManager->get('latlng') ?? '');
        if (count($pos) !== 2) {
            throw new Exception('Invalid lat-lng setting');
        }

        $this->companyLegalName = $this->settingsManager->get('company_legal_name');
        if (empty($this->companyLegalName)) {
            throw new Exception('Company name not set');
        }

        $this->companyLegalID = $this->settingsManager->get('company_legal_id');
        if (empty($this->companyLegalID)) {
            throw new Exception('Company ID not set');
        }

        $this->importFromPoint->setDefaultCoordinates(new GeoCoordinates($pos[0], $pos[1]));
        $repo = $this->entityManager->getRepository(Store::class);

        /** @var ?Store $store */
        $store = $repo->findOneBy(['transporter' => $this->transporter]);
        if (is_null($store)) {
            throw new Exception(sprintf(
                'No store with transporter "%s" connected',
                $this->transporter
            ));
        }

        $address = $store->getAddress();
        if (is_null($address)) {
            throw new Exception('Store without address');
        }

        $this->impl = new TransporterImpl($transporter);

        $this->store = $store;
        $this->HQAddress = $address;

    }

    /**
     * @throws FilesystemException
     * @throws TransporterException
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $transporterName = TransporterName::from($input->getArgument('transporter'));
        $this->transporter = $transporterName->value;

        if (!$this->lock(sprintf(
            '%s_%s_%s.lock',
            $this->appName,
            $this->getName(),
            $this->transporter
        ))) {
            $output->writeln('The command is already running in another process.');
            $this->transporterLogger->warning('The command is already running in another process.',
                ['transporter' => $this->transporter]
            );
            return Command::FAILURE;
        }

        $this->output = $output;
        $this->dryRun = $input->getOption('dry-run');

        try {
            $this->setup($transporterName);
        } catch (Exception $e) {
            $this->transporterLogger->warning(
                sprintf('Failed to setup transporter %s: %s', $this->transporter, $e->getMessage()),
                ['transporter' => $this->transporter]
            );
            throw $e;
        }

        try {
            $config = $this->params->get('transporters_config');
            if (!($config[$this->transporter]['enabled'] ?? false)) {
                $this->transporterLogger->warning(
                    sprintf('%s is not configured or enabled', $this->transporter),
                    ['transporter' => $this->transporter]
                );
                throw new Exception(sprintf('%s is not configured or enabled', $this->transporter));
            }
            $config = $config[$this->transporter];

            $this->importFromPoint->setPackageMapping(
                $this->resolvePackageMapping($config['package_mapping'] ?? [])
            );

            if (isset($config['sync']['uri'])) {
                $inFs = $outFs = $this->initTransporterSyncOptions($config['sync']);
            }
            elseif (isset($config['sync']['in']) && isset($config['sync']['out'])) {
                $inFs = $this->initTransporterSyncOptions($config['sync']['in']);
                $outFs = $this->initTransporterSyncOptions($config['sync']['out']);
            } else {
                $this->transporterLogger->critical(
                    'Sync not configured',
                    ['transporter' => $this->transporter]
                );
                return Command::FAILURE;
            }

            $opts = new TransporterOptions(
                $transporterName,
                $this->companyLegalName, $this->companyLegalID,
                $config['legal_name'], $config['legal_id'],
                $outFs, $inFs
            );

            /** @var TransporterSync $sync */
            $sync = new ($this->impl->sync)($opts);

            if ($this->dryRun) {
                $output->writeln("Dry run mode, nothing will be imported");
            }

            $this->transporterLogger->info(
                sprintf(
                    'Syncing %s with %s',
                    $this->appName,
                    $this->transporter
                ),
                ['transporter' => $this->transporter]
            );

            try {
                $this->importAllTasks($sync);
            } catch (Exception $e) {
                $this->transporterLogger->critical(
                    sprintf(
                        'Failed to import tasks: %s',
                        $e->getMessage()
                    ),
                    ['transporter' => $this->transporter]
                );
                throw $e;
            }

            try {
                $this->sendReports($sync, $opts);
            } catch (Exception $e) {
                $this->transporterLogger->critical(
                    sprintf(
                        'Failed to send reports: %s',
                        $e->getMessage()
                    ),
                    ['transporter' => $this->transporter]
                );
                throw $e;
            }

            return Command::SUCCESS;
        } finally {
            $this->release();
        }
    }

    /**
     * @throws FilesystemException
     */
    private function sendReports(TransporterSync $sync, TransporterOptions $opts): void
    {
        /** @var EDIFACTMessageRepository $repo */
        $repo = $this->entityManager->getRepository(EDIFACTMessage::class);

        $unsynced = $repo->getUnsynced($this->transporter);
        if (count($unsynced) === 0) {
            $this->output->writeln("No messages to send");
            $this->transporterLogger->info("No messages to send", ['transporter' => $this->transporter]);
            return;
        }

        $this->output->writeln(sprintf("%s messages to send", count($unsynced)));
        $reports = array_map(
            fn(EDIFACTMessage $m) => $this->reportFromCC->generateReport($m, $opts),
            $unsynced
        );

        $content = $this->reportFromCC->buildSCONTR($reports, $opts);

        // This is the name of the file that will be stored on our S3
        $filename = sprintf(
                "REPORT-%s_%s.%s.edi", mb_strtolower($this->transporter),
                date('Y-m-d_His'), uniqid()
            );

        $this->edifactFs->write($filename, $content);
        $sync->push($content);
        $ids = array_map(fn(EDIFACTMessage $m) => $m->getId(), $unsynced);
        $repo->setSynced($ids, $filename);
        $this->entityManager->flush();
    }

    /**
     * @throws FilesystemException
     * @throws TransporterException
     * @throws Exception
     */
    private function importAllTasks(TransporterSync $sync): void
    {
        $count = 0;
        foreach ($sync->pull() as $content) {
            // Some transporters declare UNOC (ISO-8859-1) in the UNB segment
            // but actually transmit UTF-8. The EDIFACT parser then strips
            // bytes 0x80-0x9F per the UNOC charset, which shreds multi-byte
            // UTF-8 "smart punctuation" (e.g. en-dash E2 80 93 -> dangling
            // E2) and yields invalid UTF-8 that PostgreSQL rejects on insert.
            // Normalize the payload before parsing; keep $content untouched so
            // the original bytes are archived verbatim on S3 below.
            $parseable = $this->normalizeEdifactPayload($content);

            [$type, $messages] = Transporter::parse(
                $parseable,
                TransporterName::from($this->transporter)
            );

            if (count($messages) > 1) {
                $this->transporterLogger->notice(
                    sprintf("%s messages to import from a single EDIFACT", count($messages)),
                );
            }

            $filename = sprintf(
                "%s-%s_%s.%s.edi",
                mb_strtoupper($type->value),
                mb_strtolower($this->transporter),
                date('Y-m-d_His'), uniqid()
            );

            if (!$this->dryRun) {
                $this->edifactFs->write($filename, $content);
            }

            foreach ($messages as $tasks) {
                foreach ($tasks->getTasks() as $task) {
                    $edifactMessage = $this->storeInboundEdi($task, $filename);
                    $this->importTask($task, $edifactMessage);
                    $count++;
                }
            }
        }
        if (!$this->dryRun) {
            $this->entityManager->flush();
        }
        $this->output->writeln("Remove files to acknowledge import");
        $this->transporterLogger->info("Remove files to acknowledge import", ['transporter' => $this->transporter]);
        $sync->flush($this->dryRun);
        $this->output->writeln("Done syncing, imported $count tasks");
        $this->transporterLogger->info("Done syncing, imported $count tasks", ['transporter' => $this->transporter]);
    }

    private function importTask(Point $point, EDIFACTMessage $edi): void {
        match($point->getType()) {
            INOVERTMessageType::SCONTR => $this->importScontrTask($point, $edi),
            INOVERTMessageType::PICKUP => $this->importPickupTask($point, $edi),
            INOVERTMessageType::DISPOR => $this->importDisporTask($point, $edi),
        };
    }

    private function importDisporTask(Point $point, EDIFACTMessage $edi): void {
        $this->importScontrTask($point, $edi);
    }

    private function importScontrTask(Point $point, EDIFACTMessage $edi): void {
        if ($this->output->isVerbose()) {
            $this->debugPoint($point);
        }

        // PICKUP SETUP
        $pickup = $this->importFromPoint
            ->buildScontr2PickupTask($this->HQAddress->clone(), $edi);

        // DROPOFF SETUP
        $dropoff = $this->importFromPoint->import($point, $edi);

        $pickup->setNext($dropoff);
        $dropoff->setPrevious($pickup);


        $pickup->setAfter($this->startOfDay());
        $pickup->setBefore($this->endOfDay());
        $dropoff->setAfter($this->startOfDay());
        $dropoff->setBefore($this->endOfDay());

        // DELIVERY SETUP
        $delivery = new Delivery();
        $delivery->setTasks([$pickup, $dropoff]);
        $delivery->setStore($this->store);

        if (!$this->dryRun) {
            $this->entityManager->persist($edi);
            $this->entityManager->persist($pickup);
            $this->entityManager->persist($dropoff);
            $this->entityManager->persist($delivery);
            $this->createOrderForDelivery($delivery);
        }
    }

    private function createOrderForDelivery(Delivery $delivery): void {
        $this->deliveryOrderManager->createOrder($delivery, [
            'pricingStrategy' => new CalculateUsingPricingRules(),
            /* 'persist' => false, */
            /* 'throwException' => false, */
        ]);
    }

    private function importPickupTask(Point $point, EDIFACTMessage $edi): void {
        if ($this->output->isVerbose()) {
            $this->debugPoint($point);
        }


        // PICKUP SETUP
        $pickup = $this->importFromPoint->import($point, $edi);

        // DROPOFF SETUP
        $dropoff = $this->importFromPoint
            ->buildPickup2DropoffTask($this->HQAddress->clone(), $edi);

        $pickup->setNext($dropoff);
        $dropoff->setPrevious($pickup);

        $pickup->setAfter($this->startOfDay());
        $pickup->setBefore($this->endOfDay());
        $dropoff->setAfter($this->startOfDay());
        $dropoff->setBefore($this->endOfDay());

        // DELIVERY SETUP
        $delivery = new Delivery();
        $delivery->setTasks([$pickup, $dropoff]);
        $delivery->setStore($this->store);

        if (!$this->dryRun) {
            $this->entityManager->persist($edi);
            $this->entityManager->persist($pickup);
            $this->entityManager->persist($dropoff);
            $this->entityManager->persist($delivery);
            $this->createOrderForDelivery($delivery);
        }
    }

    private function startOfDay(): \DateTime {
        $carbon = new Carbon();
        return $carbon->startOfDay()->toDateTime();
    }

    private function endOfDay(): \DateTime {
        $carbon = new Carbon();
        return $carbon->endOfDay()->toDateTime();
    }

    private function storeInboundEdi(Point $task, string $filename): EDIFACTMessage
    {
        $messageType = match ($task->getType()) {
            INOVERTMessageType::SCONTR => EDIFACTMessage::MESSAGE_TYPE_SCONTR,
            INOVERTMessageType::PICKUP => EDIFACTMessage::MESSAGE_TYPE_PICKUP,
            INOVERTMessageType::DISPOR => EDIFACTMessage::MESSAGE_TYPE_DISPOR,
        };
        $edi = new EDIFACTMessage();
        $edi->setMessageType($messageType);
        $edi->setReference($task->getId());
        $edi->setTransporter($this->transporter);
        $edi->setDirection(EDIFACTMessage::DIRECTION_INBOUND);
        $edi->setEdifactFile($filename);
        return $edi;
    }

    /**
     * @param array<string,mixed> $config
     */
    private function initTransporterSyncOptions(array $config = []): TransporterSyncOptions
    {
        $attributes = array_diff_key(
            $config,
            array_flip(['uri', 'pushPath', 'pullPath'])
        );

        $pushPath = isset($config['pushPath']) ? str_replace('`', "'", $config['pushPath']) : null;
        $pullPath = isset($config['pullPath']) ? str_replace('`', "'", $config['pullPath']) : null;

        // This is used for testing purposes
        if ($config['uri'] instanceof Filesystem) {
            return new TransporterSyncOptions(
                $config['uri'],
                $attributes,
                $pushPath,
                $pullPath
            );
        }

        try {
            $fs = TransporterHelpers::parseSyncOptions($config['uri']);
        } catch (\Exception $e) {
            $this->transporterLogger->critical($e->getMessage(), ['transporter' => $this->transporter]);
            throw $e;
        }


        return new TransporterSyncOptions(
            $fs,
            $attributes,
            $pushPath,
            $pullPath
        );
    }

    /**
     * Resolves the `package_mapping` config (Transporter\Enum\ProductType
     * case name => Package shortCode) into actual Package entities.
     *
     * @param array<string,string> $mapping
     * @return array<string,Package>
     */
    private function resolvePackageMapping(array $mapping): array
    {
        if (empty($mapping)) {
            return [];
        }

        $repo = $this->entityManager->getRepository(Package::class);
        $resolved = [];
        foreach ($mapping as $productType => $shortCode) {
            $package = $repo->findOneBy(['shortCode' => $shortCode]);
            if (is_null($package)) {
                $this->transporterLogger->warning(
                    sprintf(
                        'Package mapping references unknown package shortCode "%s" for product type "%s"',
                        $shortCode,
                        $productType
                    ),
                    ['transporter' => $this->transporter]
                );
                continue;
            }
            $resolved[$productType] = $package;
        }

        return $resolved;
    }

    /**
     * Makes a UTF-8 EDIFACT payload safe to feed to a parser configured for
     * the UNOC (ISO-8859-1) charset.
     *
     * The parser deletes bytes 0x80-0x9F, which are exactly the continuation
     * bytes of the common UTF-8 "smart punctuation" characters. Left alone,
     * that turns e.g. an en-dash into a lone 0xE2 lead byte (invalid UTF-8).
     * We transliterate those characters to plain ASCII up-front so they
     * survive as readable text, then guarantee the result is valid UTF-8.
     *
     * Accented Latin letters (é, è, à, ...) are intentionally left untouched:
     * their UTF-8 continuation bytes are >= 0xA0 and pass through the parser
     * unharmed, so there is no reason to strip their accents.
     */
    private function normalizeEdifactPayload(string $content): string
    {
        if (mb_check_encoding($content, 'ASCII')) {
            return $content;
        }

        // Map the characters whose UTF-8 encoding contains a 0x80-0x9F byte
        // (and would therefore be mangled) to ASCII equivalents.
        $replacements = [
            "\u{2013}" => '-',   // – en-dash
            "\u{2014}" => '-',   // — em-dash
            "\u{2018}" => "'",   // ‘ left single quote
            "\u{2019}" => "'",   // ’ right single quote
            "\u{201A}" => "'",   // ‚ single low quote
            "\u{201C}" => '"',   // “ left double quote
            "\u{201D}" => '"',   // ” right double quote
            "\u{201E}" => '"',   // „ double low quote
            "\u{2026}" => '...', // … ellipsis
            "\u{2022}" => '*',   // • bullet
            "\u{20AC}" => 'EUR', // € euro sign
            "\u{00A0}" => ' ',   // non-breaking space
        ];
        $content = strtr($content, $replacements);

        // Belt and suspenders: drop any remaining byte sequence that is not
        // valid UTF-8 so a single malformed character can never abort the
        // whole import batch.
        return mb_convert_encoding($content, 'UTF-8', 'UTF-8');
    }

    private function debugPoint(Point $point): void {
        $this->output->writeln("Task ID: ".$point->getId()."\n");
        $recipients = $point->getNamesAndAddresses(NameAndAddressType::RECIPIENT);
        $recipientAddress = isset($recipients[0]) ? $recipients[0]->getAddress() : '(none)';
        $this->output->writeln("Recipient address: " . $recipientAddress);
        $this->output->write("Times: ");
        $this->output->writeln(collect($point->getDates())->map(fn($date) => $date->getEvent()->name . ' -> ' . $date->getDate()->format('d/m/Y'))->join("\n"));
        $this->output->writeln("Number of packages: " . count($point->getPackages()));
        $this->output->writeln("Total weight: " . array_sum(array_map(fn(Mesurement $p) => $p->getQuantity(), $point->getMesurements()))." kg");
        $this->output->writeln("Comments: ".$point->getComments());
        $this->output->writeln("");
    }
}
