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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class ExportTasksCommand extends BaseExportCommand
{
    protected function configure(): void
    {
        $this->addOptions($this)
            ->setName('coopcycle:export:tasks')
            ->setDescription('Export tasks');
    }

    protected function getDatasetName(): string
    {
        return 'tasks';
    }

    protected function getEarliestModifiedAt(): ?\DateTimeInterface
    {
        $value = $this->entityManager->getConnection()
            ->executeQuery('SELECT MIN(COALESCE(updated_at, created_at)) FROM task')
            ->fetchOne();

        return $value ? new \DateTime($value) : null;
    }

    protected function exportModifiedBetween(?\DateTimeInterface $since, \DateTimeInterface $until): ?string
    {
        $envelope = $this->messageBus->dispatch(new ExportTasks(
            $since ? \DateTime::createFromInterface($since) : new \DateTime('@0'),
            \DateTime::createFromInterface($until),
            byModifiedAt: true
        ));

        /** @var HandledStamp $handledStamp */
        $handledStamp = $envelope->last(HandledStamp::class);
        return $handledStamp->getResult();
    }

    protected function exportData(\DateTimeInterface $start, \DateTimeInterface $end): ?string
    {
        $envelope = $this->messageBus->dispatch(new ExportTasks(
            $start,
            $end
        ));

        /** @var HandledStamp $handledStamp */
        $handledStamp = $envelope->last(HandledStamp::class);
        return $handledStamp->getResult();
    }

    /**
     * @param array<mixed> $row
     * @return array<mixed>
     */
    private function formatRow(array $row, ?\DateTimeInterface $exportedAt = null): array
    {
        $__s = fn (?string $s): ?string => trim($s) ?: null;
        $__dt = fn (string $d, string $t): ?\DateTimeInterface => \DateTimeImmutable::createFromFormat('j/n/Y H:i:s', sprintf('%s %s', $d, $t)) ?: null;
        $__m = fn (string $m): int => intval(floatval(str_replace(',', '.', $m)) * 100);

        [$lat, $long] = explode(',', $row['address.latlng']);

        $formatted = [
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

        if (null === $exportedAt) {
            return $formatted;
        }

        return [
            ...$formatted,
            // Carried in the file rather than parsed back out of the S3 path,
            // which used to truncate any instance name containing a hyphen.
            'instance' => $this->appName,
            'updated_at' => isset($row['updatedAt'])
                ? (\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['updatedAt']) ?: null)
                : null,
            // Version column for ClickHouse's ReplacingMergeTree: the most
            // recently exported copy of a row always wins, whatever order the
            // files are replayed in.
            'exported_at' => $exportedAt,
        ];
    }

    protected function csv2parquet(string $csv, ?\DateTimeInterface $exportedAt = null): string
    {
        // Matches the writer in ExportTasksHandler: no escape character.
        $reader = Reader::createFromString($csv);
        $reader->setEscape('');
        $reader->setHeaderOffset(0);
        $reader->addFormatter(fn($row) => $this->formatRow($row, $exportedAt));

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
            FlatColumn::enum('organization'),
            ...(null === $exportedAt ? [] : [
                FlatColumn::string('instance'),
                FlatColumn::dateTime('updated_at'),
                FlatColumn::dateTime('exported_at'),
            ])
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
