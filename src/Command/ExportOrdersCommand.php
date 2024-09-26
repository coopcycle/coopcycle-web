<?php

namespace AppBundle\Command;

use AppBundle\Message\ExportOrders;
use Flow\Parquet\ParquetFile\Compressions;
use Flow\Parquet\ParquetFile\Schema;
use Flow\Parquet\ParquetFile\Schema\FlatColumn;
use Flow\Parquet\Writer;
use League\Csv\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class ExportOrdersCommand extends BaseExportCommand
{
    protected function configure(): void
    {
        $this->addOptions($this)
            ->setName('coopcycle:export:orders')
            ->setDescription('Export orders');
    }

    protected function exportData(\DateTimeInterface $start, \DateTimeInterface $end): ?string
    {
        $envelope = $this->messageBus->dispatch(new ExportOrders(
            $start,
            $end,
            true,
            'en',
            withBillingMethod: true
        ));

        /** @var HandledStamp $handledStamp */
        $handledStamp = $envelope->last(HandledStamp::class);
        return $handledStamp->getResult();
    }

    /**
     * @param array<mixed> $row
     * @return array<mixed>
     */
    private function formatRow(array $row): array {

        if (count($row) !== 26) {
            throw new \Exception('Invalid row, expected 26 columns');
        }

        $__s = fn (string $s): ?string => trim($s) ?: null;
        $__d = fn (string $d): ?\DateTimeInterface => \DateTimeImmutable::createFromFormat('Y-m-d H:i', $d) ?: null;
        $__m = fn (string $m): int => intval(floatval(str_replace(',', '.', $m)) * 100);

        return [
            'restaurant' => $__s($row[0]),
            'order_code' => $__s($row[2]),
            'completed_at' => $__d($row[4]),
            'courier' => $__s($row[1]),
            'fullfillment' => $__s($row[3]),
            'payment_method' => $__s($row[19]),
            'delivery_fee' => $__m($row[13]),
            'tip' => $__m($row[16]),
            'promotions' => $__m($row[17]),
            'total_products_excl_vat' => $__m($row[8]),
            'total_products_incl_vat' => $__m($row[12]),
            'total_incl_tax' => $__m($row[18]),
            'stripe_fee' => $__m($row[20]),
            'platform_fee' => $__m($row[21]),
            'refunds' => $__m($row[22]),
            'net_revenue' => $__m($row[23]),
            'billing_method' => $__s($row[24]),
            'applied_billing' => $__s($row[25]),
        ];
    }

    protected function csv2parquet(string $csv): string {

        $reader = Reader::createFromString($csv)
            ->addFormatter(fn($row) => $this->formatRow($row));

        $rows = iterator_to_array($reader);
        array_shift($rows);

        $schema = Schema::with(
            FlatColumn::string('restaurant'),
            FlatColumn::string('order_code'),
            FlatColumn::dateTime('completed_at'),
            FlatColumn::string('courier'),
            FlatColumn::string('fullfillment'),
            FlatColumn::string('payment_method'),
            FlatColumn::int32('delivery_fee'),
            FlatColumn::int32('tip'),
            FlatColumn::int32('promotions'),
            FlatColumn::int32('total_products_excl_vat'),
            FlatColumn::int32('total_products_incl_vat'),
            FlatColumn::int32('total_incl_tax'),
            FlatColumn::int32('stripe_fee'),
            FlatColumn::int32('platform_fee'),
            FlatColumn::int32('refunds'),
            FlatColumn::int32('net_revenue'),
            FlatColumn::string('billing_method'),
            FlatColumn::string('applied_billing'),
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
