<?php

namespace AppBundle\Command;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Entity\ImportQueue;
use AppBundle\Entity\Store;
use AppBundle\Import\ImportRunner;
use AppBundle\Service\ImportStatusManager;
use AppBundle\Transporter\ImportFailureNotifier;
use AppBundle\Transporter\RejectedFile;
use AppBundle\Transporter\TransporterImporterInterface;
use AppBundle\Transporter\TransporterTransformerInterface;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Hashids\Hashids;
use League\Csv\Reader;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Imports the files here, rather than through the worker: the importer is told
 * which ones could not be imported, so it can keep them, and the output is what was saved.
 */
class ImportDeliveriesCommand extends Command {

    use LockableTrait;

    private InputInterface $input;
    private OutputInterface $output;

    private bool $dryRun = false;

    private ?TransporterImporterInterface $importer = null;
    private ?TransporterTransformerInterface $transformer = null;

    public function __construct(
        // Resolved on each file rather than injected: a failed import resets the entity manager
        private readonly ManagerRegistry $doctrine,
        private readonly IriConverterInterface $iriConverter,
        private readonly Hashids $hashids8,
        private readonly ImportRunner $importRunner,
        private readonly Filesystem $deliveryImportsFilesystem,
        /** @var ServiceProviderInterface<TransporterImporterInterface> */
        private readonly ServiceProviderInterface $importers,
        /** @var ServiceProviderInterface<TransporterTransformerInterface> */
        private readonly ServiceProviderInterface $transformers,
        private readonly LoggerInterface $transporterLogger,
        private readonly ImportFailureNotifier $importFailureNotifier,
        private readonly ImportStatusManager $statusManager,
        private readonly TranslatorInterface $translator,
        private readonly string $appName,
    )
    {
        parent::__construct();
    }

    protected function configure(): void {
        $this->setName('coopcycle:deliveries:import')
            ->setDescription('Imports deliveries');

        $this->addArgument('store', InputArgument::REQUIRED, 'Store name');
        $this->addArgument('csv', InputArgument::OPTIONAL, 'CSV content');

        $this->addOption('transformer', 't', InputOption::VALUE_REQUIRED, 'transformer name');
        $this->addOption('importer', 'i', InputOption::VALUE_REQUIRED, 'importer name');

        $this->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Dry run mode');

    }

    private function setup(): void {

        $importer = $this->input->getOption('importer');
        $transformer = $this->input->getOption('transformer');

        if (!$this->input->hasArgument('csv') && !is_null($importer)) {
            throw new \InvalidArgumentException('No CSV content or importer specified');
        }

        if (!is_null($importer)) {
            if (!$this->importers->has($importer)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Unknown importer "%s", available importers: "%s"',
                        $importer, implode('", "', array_keys($this->importers->getProvidedServices()))
                    )
                );
            }
            $this->importer = $this->importers->get($importer);
        }

        if (!is_null($transformer)) {
            if (!$this->transformers->has($transformer)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Unknown transformer "%s", available transformers: "%s"',
                        $transformer, implode('", "', array_keys($this->transformers->getProvidedServices()))
                    )
                );
            }
            $this->transformer = $this->transformers->get($transformer);
        }

    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $this->input = $input;
        $this->output = $output;
        $this->dryRun = $input->getOption('dry-run');

        if ($this->dryRun) {
            $this->output->writeln('Dry run, no data will be mutated');
        }

        $this->setup();

        // The files are only let go of once imported, which can take a while:
        // a run started in the meantime would pull them again, and import them twice
        if ($this->importer instanceof TransporterImporterInterface && !$this->lock(sprintf(
            '%s_%s_%s.lock',
            $this->appName,
            $this->getName(),
            $this->input->getOption('importer')
        ))) {
            $this->output->writeln('The command is already running in another process.');
            return Command::SUCCESS;
        }

        try {
            return $this->import();
        } finally {
            $this->release();
        }
    }

    private function import(): int {
        // Fetch content via the importer
        $csvs = $this->getCSVcontents();
        if (is_null($csvs)) {
            throw new \InvalidArgumentException('No CSV content or importer specified');
        }

        // As received, for the administrator to be sent a file that wasn't imported
        $received = $csvs;

        // Transform everything before importing anything. A file that can't be transformed
        // is rejected on its own, the others still go through: the importer is told on
        // flush(), so it can set it aside instead of pulling it again on every run.
        $rejected = [];
        if ($this->transformer instanceof TransporterTransformerInterface) {
            $transformed = [];
            foreach ($csvs as $key => $content) {
                try {
                    $transformed[$key] = $this->transformer->transform($content);
                } catch (\Exception $e) {
                    // A file given on the command line: nothing to set aside, just fail
                    if (!is_null($this->input->getArgument('csv'))) {
                        throw $e;
                    }
                    $this->transporterLogger->critical(
                        sprintf('Rejected file "%s": %s', $key, $e->getMessage()),
                        ['importer' => $this->input->getOption('importer'), 'store' => $this->input->getArgument('store')]
                    );
                    $this->output->writeln(sprintf('<error>Rejected file "%s": %s</error>', $key, $e->getMessage()));
                    $rejected[$key] = new RejectedFile((string) $key, $e->getMessage(), $content);
                }
            }
            $csvs = $transformed;
        }

        $failed = false;
        if ($this->dryRun) {
            foreach ($csvs as $csv) {
                $this->output->writeln($csv);
            }

            $deliveries_count = array_sum(
                array_map(
                    // Not a line count: quoted fields may span several lines.
                    // Without header offset, as a malformed header would throw here.
                    fn(string $csv): int => max(0, count(Reader::fromString($csv)) - 1),
                    $csvs
                )
            );

            $this->output->writeln(">> {$deliveries_count} deliveries to import");
        } else {
            foreach ($csvs as $key => $csv) {
                [$saved, $status, $error] = $this->process($csv);

                $this->output->writeln(sprintf('>> "%s": %d deliveries imported, the import is %s', $key, $saved, $status));

                // Some rows may have failed validation, the import tells which
                $failed = $failed || ImportQueue::STATUS_FAILED === $status;

                if (is_null($error)) {
                    continue;
                }

                $this->output->writeln(sprintf('<error>Failed to import "%s": %s</error>', $key, $error));

                // Nothing of it was saved: the importer keeps it, to be pulled again on the next run.
                // A file given on the command line can just be given again.
                if ($this->importer instanceof TransporterImporterInterface) {
                    $rejected[$key] = new RejectedFile((string) $key, $error, $received[$key]);
                }
            }
        }

        if ($this->importer instanceof TransporterImporterInterface) {
            $this->importer->flush($this->dryRun, RejectedFile::reasons($rejected));
        }

        // Only an importer rejects files: one given on the command line fails the command
        if (!$this->dryRun && !empty($rejected)) {
            $this->importFailureNotifier->notify($this->input->getOption('importer'), $rejected);
        }

        return empty($rejected) && !$failed ? Command::SUCCESS : Command::FAILURE;
    }


    private function getCSVcontents(): ?array
    {
        $csvPath = $this->input->getArgument('csv');
        if (!is_null($csvPath)) {
            $csv = file_get_contents($csvPath);
            if (empty($csv)) {
                return null;
            }
            return [$csvPath => $csv];
        }

        if ($this->importer instanceof TransporterImporterInterface) {
            return $this->importer->pull();
        }

        return null;
    }

    /**
     * @return array{0: int, 1: string, 2: ?string} The number of deliveries saved, the status
     *                                               of the import, and why nothing was saved
     *                                               when it failed without saving any
     */
    private function process(string $csv): array
    {
        /** @var Store $store */
        $store = $this->iriConverter->getResourceFromIri($this->input->getArgument('store'));

        $queue = new ImportQueue(ImportQueue::TYPE_DELIVERIES);
        $queue->setStore($store);
        $queue->setOrganization($store->getOrganization());

        $entityManager = $this->doctrine->getManager();
        $entityManager->persist($queue);
        $entityManager->flush();

        $now = new DateTime();
        $nowFormatted = $now->format('Y-m-d_G:i:s');

        $filename = sprintf('%s_%s_%s.%s', 'command_import', $nowFormatted, $this->hashids8->encode($queue->getId()), 'csv');

        $this->deliveryImportsFilesystem->write($filename, $csv);

        $queue->setFilename($filename);
        $entityManager->flush();

        $saved = 0;
        $error = null;
        try {
            $saved = $this->importRunner->run(
                $filename,
                ['create_task_if_address_not_geocoded' => true],
                $queue->getId()
            );
        } catch (\Throwable $e) {
            $error = $e->getMessage();

            $this->transporterLogger->error(
                sprintf('Importing deliveries from %s failed: %s', $filename, $error),
                ['importer' => $this->input->getOption('importer'), 'store' => $this->input->getArgument('store')]
            );

            // The runner marks the import as failed when saving what it read fails,
            // the worker otherwise (see ImportFailureSubscriber), which isn't there
            $unfinished = $this->statusManager->findUnfinished($filename);
            if (null !== $unfinished) {
                $this->statusManager->failed($unfinished, ImportStatusManager::errorForWholeImport(
                    $this->translator->trans('deliveries.imports.error.interrupted')
                ));
            }
        }

        $status = $this->statusManager->currentStatus($queue);

        if ($saved > 0 || ImportQueue::STATUS_FAILED !== $status) {
            return [$saved, $status, null];
        }

        return [0, $status, $error ?? 'No delivery could be imported, see the errors of the import'];
    }

}
