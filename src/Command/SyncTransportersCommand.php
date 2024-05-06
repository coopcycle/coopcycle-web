<?php

namespace AppBundle\Command;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Edifact\EDIFACTMessageRepository;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Service\Geocoder;
use AppBundle\Service\SettingsManager;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Transporter\DTO\CommunicationMean;
use Transporter\DTO\Mesurement;
use Transporter\DTO\NameAndAddress;
use Transporter\DTO\Point;
use Transporter\Enum\CommunicationMeanType;
use Transporter\Enum\INOVERTMessageType;
use Transporter\Enum\NameAndAddressType;
use Transporter\Enum\TransporterName;
use Transporter\Transporter;
use Transporter\TransporterException;
use Transporter\TransporterOptions;
use Transporter\Transporters\DBSchenker\DBSchenkerSync;
use Transporter\Transporters\DBSchenker\Generator\DBSchenkerInterchange;
use Transporter\Transporters\DBSchenker\Generator\DBSchenkerReport;
use Transporter\Transporters\DBSchenker\Parser\DBSchenkerScontrParser;

class SyncTransportersCommand extends Command {

    private Address $HQAddress;
    private Store $store;
    private GeoCoordinates $defaultCoordinates;

    private string $companyLegalName;
    private string $companyLegalID;

    private bool $dryRun;
    private OutputInterface $output;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $params,
        private SettingsManager $settingsManager,
        private Geocoder $geocoder,
        private PhoneNumberUtil $phoneUtil,
        private Filesystem $edifactFs
    )
    { parent::__construct(); }

    protected function configure(): void
    {
        $this->setName('coopcycle:transporters:sync')
        ->setDescription('Synchronizes transporters');

        $this->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Dry run mode');
    }

    /**
     * @throws Exception
     */
    private function setup(): void
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

        $this->defaultCoordinates = new GeoCoordinates($pos[0], $pos[1]);
        $repo = $this->entityManager->getRepository(Store::class);

        /** @var ?Store $store */
        $store = $repo->findOneBy(['DBSchenkerEnabled' => true]);
        if (is_null($store)) {
            //TODO: Do not throw to avoid log pollution
            throw new Exception('No store with transporter connected');
        }

        $address = $store->getAddress();
        if (is_null($address)) {
            throw new Exception('Store without address');
        }

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
        $this->setup();
        $this->dryRun = $input->getOption('dry-run');
        $this->output = $output;

        $config = $this->params->get('transporters_config');
        if (!($config['DBSCHENKER']['enabled'] ?? false)) {
            throw new Exception('DBSchenker is not configured or enabled');
        }
        $config = $config['DBSCHENKER'];

        $auth_details = parse_url($config['sync_uri']);

        $adapter = new FtpAdapter(
            FtpConnectionOptions::fromArray([
                'host' => $auth_details['host'],
                'username' => $auth_details['user'],
                'password' => $auth_details['pass'],
                'port' => $auth_details['port'] ?? 21,
                'root' => $auth_details['path'] ?? '',
                'ssl' => false,
            ])
        );

        $filesystem = new Filesystem($adapter);

        $opts = new TransporterOptions(
            $this->companyLegalName, $this->companyLegalID,
            $config['legal_name'], $config['legal_id'],
            $filesystem, $config['fs_mask'],
        );
        $sync = new DBSchenkerSync($opts);

        if ($this->dryRun) {
            $output->writeln("Dry run mode, nothing will be imported");
        }
        $output->writeln("Start syncing...");

        $this->importAllTasks($sync);
        $this->sendReports($sync, $opts);

        return 0;
    }

    /**
     * @throws FilesystemException
     */
    private function sendReports(DBSchenkerSync $sync, TransporterOptions $opts): void
    {
        /** @var EDIFACTMessageRepository $repo */
        $repo = $this->entityManager->getRepository(EDIFACTMessage::class);

        $unsynced = $repo->getUnsynced();
        if (count($unsynced) === 0) {
            $this->output->writeln("No messages to send");
            return;
        }

        $this->output->writeln(sprintf("%s messages to send", count($unsynced)));
        $reports = array_map(fn(EDIFACTMessage $m) => $this->generateReport($m, $opts), $unsynced);
        $out = new DBSchenkerInterchange($opts);

        foreach ($reports as $report) {
            $out->addGenerator($report);
        }

        $content = $out->generate();
        $filename = sprintf(
                "REPORT-%s_%s.%s.edi", "dbschenker",
                date('Y-m-d_His'), uniqid()
            );

        $this->edifactFs->write($filename, $content);
        $sync->push($content);
        $ids = array_map(fn(EDIFACTMessage $m) => $m->getId(), $unsynced);
        $repo->setSynced($ids, $filename);
    }

    private function generateReport(EDIFACTMessage $message, TransporterOptions $opts): DBSchenkerReport
    {
        $report = new DBSchenkerReport($opts);
        $report->setDocID(strval($message->getId()));
        $report->setReference($message->getReference());
        $report->setReceipt($message->getReference());
        [$situation, $reason] = explode('|', $message->getSubMessageType());
        $report->setSituation(constant("Transporter\Enum\ReportSituation::$situation"));
        $report->setReason(constant("Transporter\Enum\ReportReason::$reason"));
        return $report;
    }

    /**
     * @throws FilesystemException
     * @throws TransporterException
     * @throws Exception
     */
    private function importAllTasks(DBSchenkerSync $sync): void
    {
        $count = 0;
        foreach ($sync->pull() as $content) {
            $messages = Transporter::parse(
                $content,
                INOVERTMessageType::SCONTR,
                TransporterName::DB_SCHENKER
            );
            $filename = sprintf(
                "%s_%s-SCONTR.%s.edi", "dbschenker",
                date('Y-m-d_His'), uniqid()
            );

            if (!$this->dryRun) {
                $this->edifactFs->write($filename, $content);
            }

            foreach ($messages as $tasks) {
                if ($tasks instanceof DBSchenkerScontrParser) {
                    foreach ($tasks->getTasks() as $task) {
                        $edifactMessage = $this->storeSCONTR($task, $filename);
                        $this->importTask($task, $edifactMessage);
                        $count++;
                    }
                }
            }
        }
        $this->output->writeln("Remove files to acknowledge import");
        $sync->flush($this->dryRun);
        $this->output->writeln("Done syncing, imported $count tasks");
    }

    private function importTask(Point $task, EDIFACTMessage $edi): void {
        if ($this->output->isVerbose()) {
            $this->output->writeln("Task ID: ".$task->getId()."\n");
            $this->output->writeln("Recipient address: ".$task->getNamesAndAddresses(NameAndAddressType::RECIPIENT)[0]->getAddress());
            $this->output->write("Times: ");
            $this->output->writeln(collect($task->getDates())->map(fn($date) => $date->getEvent()->name . ' -> ' . $date->getDate()->format('d/m/Y'))->join("\n"));
            $this->output->writeln("Number of packages: " . count($task->getPackages()));
            $this->output->writeln("Total weight: " . array_sum(array_map(fn(Mesurement $p) => $p->getQuantity(), $task->getMesurements()))." kg");
            $this->output->writeln("Comments: ".$task->getComments());
            $this->output->writeln("");
        }

        // PICKUP SETUP
        $pickup = $this->generatePickupTask();

        // DROPOFF SETUP
        $nad = $task->getNamesAndAddresses(NameAndAddressType::RECIPIENT);
        if (count($nad) !== 1) {
            throw new Exception("Cannot handle multiple recipients");
        }
        $nad = $nad[0];
        $address = $this->DBSchenkerToCCAddress($nad);

        $imported_from = sprintf(
            "%s\n%s\n\n%s\n",
            $nad->getAddressLabel(),
            $nad->getAddress(),
            $nad->getContactName()
        );
        $imported_from .= collect($nad->getCommunicationMeans())
        ->map(fn(CommunicationMean $c) => $c->getType()->name . ': ' . $c->getValue())
        ->join("\n");

        $CCTask = new Task();
        $CCTask->setPrevious($pickup);
        $CCTask->setAddress($address);
        $CCTask->setComments($task->getComments());
        $CCTask->setMetadata('imported_from', $imported_from);
        $CCTask->addEdifactMessage($edi);

        if ($address->getGeo()->isEqualTo($this->defaultCoordinates)) {
            $this->output->writeln("Address without coordinates: ".$nad->getAddress());
            $CCTask->setTags('review-needed');
            //TODO: Trigger a incident ??
        }

        $weight = array_sum(array_map(
            fn(Mesurement $p) => $p->getQuantity(),
            $task->getMesurements()
        ));
        $CCTask->setWeight($weight * 1000);

        if (str_contains($task->getProductClass()->name, 'PREMIUM')) {
            $CCTask->setTags('premium');
        }

        //TODO: Maybe add package codes

        // DELIVERY SETUP
        $delivery = new Delivery();
        $delivery->setTasks([$pickup, $CCTask]);
        $delivery->setStore($this->store);
        $delivery->setPickupRange($this->startOfDay(), $this->endOfDay());
        $delivery->setDropoffRange($this->startOfDay(), $this->endOfDay());

        if (!$this->dryRun) {
            $this->entityManager->persist($edi);
            $this->entityManager->persist($pickup);
            $this->entityManager->persist($CCTask);
            $this->entityManager->persist($delivery);
            $this->entityManager->flush();
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

    private function generatePickupTask(): Task
    {
        $task = new Task();
        $task->setType(Task::TYPE_PICKUP);
        $task->setAddress($this->HQAddress->clone());
        return $task;
    }

    private function DBSchenkerToCCAddress(NameAndAddress $nad): Address
    {
        $address = $this->geocoder->geocode($nad->getAddress());

        if (is_null($address)) {
            $address = new Address();
            $address->setGeo($this->defaultCoordinates);
            $address->setStreetAddress('INVALID ADDRESS');
        }


        $address->setCompany($nad->getAddressLabel());
        $address->setName($nad->getAddressLabel());
        $address->setContactName($nad->getContactName());
        $address->setTelephone($this->DBSchenkerToCCPhone($nad->getCommunicationMeans()));

        return $address;
    }

    /**
     * @param array<CommunicationMean> $communicationMeans
     */
    private function DBSchenkerToCCPhone(array $communicationMeans): ?PhoneNumber
    {
        $phone = collect($communicationMeans)
        ->filter(fn(CommunicationMean $c) => $c->getType() === CommunicationMeanType::PHONE)
        ->map(fn(CommunicationMean $c) => $c->getValue())
        ->first();

        if (!is_null($phone)) {
            try {
                //TODO: Handle country code
                $phone = $this->phoneUtil->parse($phone, 'FR');
            } catch (Exception $e) {
                return null;
            }
        }

        return $phone;

    }

    private function storeSCONTR(Point $task, string $filename): EDIFACTMessage
    {
        $edi = new EDIFACTMessage();
        $edi->setMessageType("SCONTR");
        $edi->setReference($task->getId());
        $edi->setTransporter(EDIFACTMessage::TRANSPORTER_DBSCHENKER);
        $edi->setDirection(EDIFACTMessage::DIRECTION_INBOUND);
        $edi->setEdifactFile($filename);
        return $edi;
    }
}
