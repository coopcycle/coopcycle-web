<?php

namespace AppBundle\Command;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Entity\Delivery\ImportQueue;
use AppBundle\Entity\Store;
use AppBundle\Message\ImportDeliveries;
use AppBundle\Transporter\TransporterImporterInterface;
use AppBundle\Transporter\TransporterTransformerInterface;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use League\Csv\Reader;
use League\Flysystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\Service\ServiceProviderInterface;

class ImportDeliveriesCommand extends Command {


    private InputInterface $input;
    private OutputInterface $output;

    private bool $dryRun = false;

    private ?TransporterImporterInterface $importer = null;
    private ?TransporterTransformerInterface $transformer = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly IriConverterInterface $iriConverter,
        private readonly Hashids $hashids8,
        private readonly MessageBusInterface $messageBus,
        private readonly Filesystem $deliveryImportsFilesystem,
        /** @var ServiceProviderInterface<TransporterImporterInterface> */
        private readonly ServiceProviderInterface $importers,
        /** @var ServiceProviderInterface<TransporterTransformerInterface> */
        private readonly ServiceProviderInterface $transformers
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

        // Fetch content via the importer
        $csvs = $this->getCSVcontents();
        if (is_null($csvs)) {
            throw new \InvalidArgumentException('No CSV content or importer specified');
        }

        // Transform everything before queuing anything, so a bad file
        // doesn't leave the import half done (and re-imported on next run)
        if ($this->transformer instanceof TransporterTransformerInterface) {
            $csvs = array_map(fn($content): string => $this->transformer->transform($content), $csvs);
        }

        foreach ($csvs as $csv) {

            // Dry run, console output then continue
            if ($this->dryRun) {
                $this->output->writeln($csv);
                continue;
            }

            $this->process(
                $this->iriConverter->getResourceFromIri($this->input->getArgument('store')),
                $csv
            );
        }

        if ($this->importer instanceof TransporterImporterInterface) {
            $this->importer->flush($this->dryRun);
        }

        // Count deliveries
        $deliveries_count = array_sum(
            array_map(
                // Not a line count: quoted fields may span several lines
                fn(string $csv): int => count(Reader::fromString($csv)->setHeaderOffset(0)),
                $csvs
            )
        );

        $this->output->writeln(">> {$deliveries_count} deliveries imported");
        return Command::SUCCESS;
    }


    private function getCSVcontents(): ?array
    {
        $csvPath = $this->input->getArgument('csv');
        if (!is_null($csvPath)) {
            $csv = file_get_contents($csvPath);
            if (empty($csv)) {
                return null;
            }
            return [$csv];
        }

        if ($this->importer instanceof TransporterImporterInterface) {
            return $this->importer->pull();
        }

        return null;
    }

    private function process(Store $store, string $csv): ImportQueue
    {
        $queue = new ImportQueue();

        $queue->setStore($store);

        $this->entityManager->persist($queue);
        $this->entityManager->flush();

        $now = new DateTime();
        $nowFormatted = $now->format('Y-m-d_G:i:s');

        $filename = sprintf('%s_%s_%s.%s', 'command_import', $nowFormatted, $this->hashids8->encode($queue->getId()), 'csv');

        $this->deliveryImportsFilesystem->write($filename, $csv);

        $queue->setFilename($filename);
        $this->entityManager->flush();

        $this->messageBus->dispatch(
            new ImportDeliveries($filename, ['create_task_if_address_not_geocoded' => true]),
            [ new DelayStamp(5000) ]
        );

        return $queue;
    }

}
