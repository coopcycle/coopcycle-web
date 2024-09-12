<?php

namespace AppBundle\Command;

use AppBundle\Message\ExportOrders;
use DateTime;
use Flow\Parquet\ParquetFile\Compressions;
use Flow\Parquet\ParquetFile\Schema;
use Flow\Parquet\ParquetFile\Schema\FlatColumn;
use Flow\Parquet\Writer;
use League\Csv\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class ExportOrdersCommand extends BaseExportCommand
{
    use LockableTrait;

    public function __construct(
        private MessageBusInterface $messageBus
    )
    { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOptions($this)
            ->setName('coopcycle:export:orders')
            ->setDescription('Export orders');
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

        $envelope = $this->messageBus->dispatch(new ExportOrders(
            $this->parseDate($input->getOption('date-start')),
            $this->parseDate($input->getOption('date-end')),
            true
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

        if (count($row) !== 24) {
            throw new \Exception('Invalid row, expected 24 columns');
        }

        $__s = fn (string $s): ?string => trim($s) ?: null;
        $__dt = fn (string $d): ?\DateTime => \DateTime::createFromFormat('d/m/Y H:i:s', $d) ?: null;

        return [
            'restaurant' => $__s($row[0]),
            'order_code' => $__s($row[2]),
            'completed_at' => $__dt($row[4]),
            'courier' => $__s($row[1]),
            'fullfillment' => $__s($row[3]),
            'payment_method' => $__s($row[19]),
            'delivery_fee' => floatval($row[13]),
            'tip' => floatval($row[16]),
            'promotions' => floatval($row[17]),
            'total_products_excl_vat' => floatval($row[8]),
            'total_products_incl_vat' => floatval($row[12]),
            'total_vat' => floatval($row[18]),
            'stripe_fee' => floatval($row[20]),
            'platform_fee' => floatval($row[21]),
            'refunds' => floatval($row[22]),
            'net_revenue' => floatval($row[23]),
        ];
    }


    private function csv2parquet(string $csv): string {

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
            FlatColumn::float('delivery_fee'),
            FlatColumn::float('tip'),
            FlatColumn::float('promotions'),
            FlatColumn::float('total_products_excl_vat'),
            FlatColumn::float('total_products_incl_vat'),
            FlatColumn::float('total_vat'),
            FlatColumn::float('stripe_fee'),
            FlatColumn::float('platform_fee'),
            FlatColumn::float('refunds'),
            FlatColumn::float('net_revenue')
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
