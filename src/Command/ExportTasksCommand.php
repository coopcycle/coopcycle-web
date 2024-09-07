<?php

namespace AppBundle\Command;

use AppBundle\Message\ExportTasks;
use Flow\Parquet\ParquetFile\Compressions;
use Flow\Parquet\ParquetFile\Schema;
use Flow\Parquet\ParquetFile\Schema\FlatColumn;
use Flow\Parquet\ParquetFile\Schema\NestedColumn;
use Flow\Parquet\Writer;
use League\Csv\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class ExportTasksCommand extends Command
{
    use LockableTrait;

    public function __construct(
        private MessageBusInterface $messageBus
    )
    { parent::__construct(); }

    protected function configure(): void
    {
        $this
            ->setName('coopcycle:export:tasks')
            ->setDescription('Export tasks')
            ->addOption(
                'date-start', 's',
                InputOption::VALUE_OPTIONAL,
                'Start date',
                (new \DateTime())->modify('-1 day')->setTime(0, 0, 1)
            )
            ->addOption(
                'date-end', 'd',
                InputOption::VALUE_OPTIONAL,
                'End date',
                (new \DateTime())->modify('-1 day')->setTime(23, 59, 59)
            )
            ->addOption(
                'target', 't',
                InputOption::VALUE_REQUIRED,
                'Target directory'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');
            return Command::FAILURE;
        }

        $envelope = $this->messageBus->dispatch(new ExportTasks(
            $this->parseDate($input->getOption('date-start')),
            $this->parseDate($input->getOption('date-end'))
        ));

        /** @var HandledStamp $handledStamp */
        $handledStamp = $envelope->last(HandledStamp::class);
        $csv = $handledStamp->getResult();

        $parquet = $this->csv2parquet($csv);

        file_put_contents('/tmp/tasks.parquet', $parquet);

        return Command::SUCCESS;
    }

    private function parseDate($date): \DateTime
    {
        if ($date instanceof \DateTime) {
            return $date;
        }

        return \DateTime::createFromFormat('Y-m-d', $date);
    }


    private function formatRow(array $row): array {
        [$lat, $long] = explode(',', $row['address.latlng']);

        return [
            'id' => intval($row['#']),
            'order_id' => intval($row['# order']),
            'order_code' => $row['orderCode'],
            'order_total' => floatval($row['orderTotal']),
            'order_revenue' => floatval($row['orderRevenue']),
            'type' => $row['type'],
            'address' => [
                'contact' => $row['address.contactName'],
                'name' => $row['address.name'],
                'street' => $row['address.streetAddress'],
                'description' => $row['address.description'],
                'lat' => floatval($lat),
                'lng' => floatval($long),
            ],
            'after' => \DateTime::createFromFormat('d/m/Y H:i:s', sprintf('%s %s', $row['afterDay'], $row['afterTime'])) ?: null,
            'before' => \DateTime::createFromFormat('d/m/Y H:i:s', sprintf('%s %s', $row['beforeDay'], $row['beforeTime'])) ?: null,
            'status' => $row['status'],
            'finished' => \DateTime::createFromFormat('d/m/Y H:i:s', sprintf('%s %s', $row['finishedAtDay'], $row['finishedAtTime'])) ?: null,
            'courier' => $row['courier'],
            'organization' => $row['organization'],
        ];
    }

    private function csv2parquet(string $csv): string {

        $reader = Reader::createFromString($csv)
            ->setHeaderOffset(0)
            ->addFormatter(fn($row) => $this->formatRow($row));

        $rows = iterator_to_array($reader);

        $schema = Schema::with(
            FlatColumn::int32('id'),
            FlatColumn::int32('order_id'),
            FlatColumn::string('order_code'),
            FlatColumn::float('order_total'),
            FlatColumn::float('order_revenue'),
            FlatColumn::enum('type'),
            NestedColumn::struct('address', [
                FlatColumn::string('contact'),
                FlatColumn::string('name'),
                FlatColumn::string('street'),
                FlatColumn::string('description'),
                FlatColumn::float('lat'),
                FlatColumn::float('lng'),
            ]),
            FlatColumn::dateTime('after'),
            FlatColumn::dateTime('before'),
            FlatColumn::enum('status'),
            FlatColumn::dateTime('finished'),
            FlatColumn::enum('courier'),
            FlatColumn::enum('organization')
        );

        $writer = new Writer(Compressions::GZIP);

        $tmp = sys_get_temp_dir() . '/' . uniqid() . '.parquet';
        $writer->open($tmp, $schema);
        $writer->writeBatch($rows);
        $writer->close();
        $csv = file_get_contents($tmp);
        unlink($tmp);
        return $csv;
    }

}
