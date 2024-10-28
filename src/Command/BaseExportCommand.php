<?php

namespace AppBundle\Command;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

abstract class BaseExportCommand extends Command {

    use LockableTrait;

    public function __construct(protected string $appName, protected MessageBusInterface $messageBus)
    {
        parent::__construct();
    }

    protected function addOptions(self $cmd): self
    {
        return $cmd
            ->addOption(
                'date-start', null,
                InputOption::VALUE_REQUIRED,
                'Start date',
                (new \DateTime())->modify('-1 day')->setTime(0, 0, 1)
            )
            ->addOption(
                'date-end', null,
                InputOption::VALUE_REQUIRED,
                'End date',
                (new \DateTime())->modify('-1 day')->setTime(23, 59, 59)
            )
            ->addOption(
                'target', 't',
                InputOption::VALUE_REQUIRED,
                'Target directory'
            )
            ->addOption(
                's3-access-key', null,
                InputOption::VALUE_REQUIRED,
                'S3 access key'
            )
            ->addOption(
                's3-secret-key', null,
                InputOption::VALUE_REQUIRED,
                'S3 secret key'
            )
            ->addOption(
                'format', 'f',
                InputOption::VALUE_REQUIRED,
                'Output format'
            )
            ->addOption('unsecure', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lockName = sprintf('%s_%s', $this->appName, $this->getName());
        if (!$this->lock($lockName)) {
            $output->writeln('The command is already running in another process.');
            return Command::FAILURE;
        }

        [$target, $options] = $this->parseTarget(
            $input->getOption('target'),
            $input->getOption('unsecure')
        );

        // TODO Validate target & format here

        foreach ($this->getDatePeriod($input) as $date) {

            $export = $this->exportData(clone $date, clone $date);

            if (empty($export)) {
                continue;
            }

            switch ($input->getOption('format')) {
                case 'parquet':
                    $export = $this->csv2parquet($export);
                    break;
            }

            switch ($target) {
                case 's3':

                    $path = sprintf('%s/%s', $options['key'], $this->getHivePartitioningPath($date, $input->getOption('format')));

                    $this->pushToS3(
                        $path,
                        $export,
                        $options,
                        $input->getOption('s3-access-key'),
                        $input->getOption('s3-secret-key')
                    );

                    break;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<mixed>
     */
    protected function parseTarget(string $target, bool $unsecure = false): array
    {
        $parsed = parse_url($target);
        if (!$parsed) {
            throw new \Exception('Invalid target');
        }
        switch (strtolower($parsed['scheme'])) {
            case 's3':
                $_path = explode('/', ltrim($parsed['path'], '/'));
                $parsed['bucket'] = $_path[0];
                unset($_path[0]);
                $parsed['path'] = implode('/', $_path);
                return [
                    's3',
                    [
                        'endpoint' => sprintf('%s://%s',
                            $unsecure ? 'http' : 'https',
                            implode(':', array_filter(
                                [$parsed['host'], $parsed['port'] ?? null]
                            ))),
                        'bucket' => $parsed['bucket'],
                        'key' => $parsed['path']
                    ]
                ];
            default:
                throw new \Exception('Unsupported scheme');
        }
    }

    /**
     * @param mixed $date
     */
    protected function parseDate($date): \DateTime
    {
        if ($date instanceof \DateTime) {
            return $date;
        }

        return \DateTime::createFromFormat('Y-m-d', $date);
    }

    /**
     * @param array{endpoint: string, bucket: string, key: string} $options
     */
    protected function pushToS3(
        string $location,
        string $contents,
        array $options,
        string $accessKey,
        string $secretKey,
        bool   $pathStyle = true
    ): void {
        $client = new S3Client([
            'endpoint' => $options['endpoint'],
            'use_path_style_endpoint' => $pathStyle,
            'region' => 'fr-fr',
            'credentials' => [
                'key' => $accessKey,
                'secret' => $secretKey
            ]
        ]);

        $adapter = new AwsS3V3Adapter($client, $options['bucket']);
        $filesystem = new Filesystem($adapter);
        $filesystem->write($location, $contents);
    }

    protected function getDatePeriod(InputInterface $input): \DatePeriod
    {
        return new \DatePeriod(
            $this->parseDate($input->getOption('date-start')),
            \DateInterval::createFromDateString('1 day'),
            $this->parseDate($input->getOption('date-end')),
            \DatePeriod::INCLUDE_END_DATE
        );
    }

    protected function getHivePartitioningPath(\DateTimeInterface $date, string $format): string
    {
        return sprintf('year=%s/month=%s/%s.%s',
            $date->format('Y'), $date->format('m'), $date->format('Y-m-d'), $format);
    }

    abstract protected function exportData(\DateTimeInterface $start, \DateTimeInterface $end): ?string;

    abstract protected function csv2parquet(string $csv): string;
}
