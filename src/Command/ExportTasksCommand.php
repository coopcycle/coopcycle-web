<?php

namespace AppBundle\Command;

use AppBundle\Message\ExportTasks;
use DateTime;
use Flow\Parquet\ParquetFile\Compressions;
use Flow\Parquet\ParquetFile\Schema;
use Flow\Parquet\ParquetFile\Schema\FlatColumn;
use Flow\Parquet\ParquetFile\Schema\NestedColumn;
use Flow\Parquet\Writer;
use League\Csv\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class ExportTasksCommand extends BaseExportCommand
{
    use LockableTrait;

    public function __construct(
        private MessageBusInterface $messageBus
    )
    { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOptions($this)
            ->setName('coopcycle:export:tasks')
            ->setDescription('Export tasks');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');
            return Command::FAILURE;
        }

        [$target, $options] = $this->parseTarget(
            $input->getOption('target'),
            $input->getOption('unsecure')
        );

        $envelope = $this->messageBus->dispatch(new ExportTasks(
            $this->parseDate($input->getOption('date-start')),
            $this->parseDate($input->getOption('date-end'))
        ));

        /** @var HandledStamp $handledStamp */
        $handledStamp = $envelope->last(HandledStamp::class);
        $export = $handledStamp->getResult();

        switch ($input->getOption('format')) {
            case 'parquet':
                $export = $this->csv2parquet($export);
                break;
            case 'csv': break;
            default:
                throw new \Exception('Unsupported format');
        }

        switch ($target) {
            case 's3':
                $this->pushToS3(
                    $export,
                    $options,
                    $input->getOption('s3-access-key'),
                    $input->getOption('s3-secret-key')
                );
                break;
            default:
                throw new \Exception('Unsupported target');
        }


        return Command::SUCCESS;
    }


    /**
     * @param array<mixed> $row
     * @return array<mixed>
     */
    private function formatRow(array $row): array {
        $__s = fn (string $s): ?string => trim($s) ?: null;
        $__dt = fn (string $d, string $t): ?\DateTimeInterface => \DateTimeImmutable::createFromFormat('j/n/Y H:i:s', sprintf('%s %s', $d, $t)) ?: null;
        $__m = fn (string $m): int => intval(floatval($m) * 100);

        [$lat, $long] = explode(',', $row['address.latlng']);

        return [
            'id' => intval($row['#']),
            'order_id' => intval($row['# order']) ?: null,
            'order_code' => $__s($row['orderCode']),
            'order_total' => $__m(($row['orderTotal'])),
            'order_revenue' => $__m($row['orderRevenue']),
            'type' => $row['type'],
            'address' => [
                'contact' => $__s($row['address.contactName']),
                'name' => $__s($row['address.name']),
                'street' => $row['address.streetAddress'],
                'description' => $__s($row['address.description']),
                'lat' => floatval($lat),
                'lng' => floatval($long),
            ],
            'after' => $__dt($row['afterDay'], $row['afterTime']),
            'before' => $__dt($row['beforeDay'], $row['beforeTime']),
            'status' => $row['status'],
            'finished' => $__dt($row['finishedAtDay'], $row['finishedAtTime']),
            'courier' => $__s($row['courier']),
            'organization' => $__s($row['organization']),
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
            FlatColumn::int32('order_total'),
            FlatColumn::int32('order_revenue'),
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

        // Temporary file, no choice the library API is poorly designed.
        // and i'm too lazy to implement it a DestinationStream class.
        $tmp = sys_get_temp_dir() . '/' . uniqid() . '.parquet';
        $writer->open($tmp, $schema);

        $writer->writeBatch($rows);

        $writer->close();
        $csv = file_get_contents($tmp);
        unlink($tmp);

        return $csv;
    }

 }
