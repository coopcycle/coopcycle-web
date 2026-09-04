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
                (new \DateTime())->modify('-1 day')->setTime(0, 0, 1)->format('Y-m-d')
            )
            ->addOption(
                'date-end', null,
                InputOption::VALUE_REQUIRED,
                'End date',
                (new \DateTime())->modify('-1 day')->setTime(23, 59, 59)->format('Y-m-d')
            )
            ->addOption(
                'target', 't',
                InputOption::VALUE_REQUIRED,
                'Target directory'
            )
            // Credentials default to the environment so that callers do not have
            // to put them on the command line, where they leak into "ps" output
            // and into the command string this app logs when a command fails.
            ->addOption(
                's3-access-key', null,
                InputOption::VALUE_REQUIRED,
                'S3 access key (defaults to COOPCYCLE_S3_ACCESS_KEY)',
                $_SERVER['COOPCYCLE_S3_ACCESS_KEY'] ?? $_ENV['COOPCYCLE_S3_ACCESS_KEY'] ?? null
            )
            ->addOption(
                's3-secret-key', null,
                InputOption::VALUE_REQUIRED,
                'S3 secret key (defaults to COOPCYCLE_S3_SECRET_KEY)',
                $_SERVER['COOPCYCLE_S3_SECRET_KEY'] ?? $_ENV['COOPCYCLE_S3_SECRET_KEY'] ?? null
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

        // Fail before doing any work rather than exporting every day in the
        // period and getting a 403 on each PutObject.
        if ('s3' === $target
            && (empty($input->getOption('s3-access-key')) || empty($input->getOption('s3-secret-key')))) {
            throw new \InvalidArgumentException(
                'Missing S3 credentials: pass --s3-access-key/--s3-secret-key or set COOPCYCLE_S3_ACCESS_KEY/COOPCYCLE_S3_SECRET_KEY.'
            );
        }

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

                case 'file':
                    file_put_contents($options['path'], $export);
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
            case 'file':
                return [
                    'file', [ 'path' => $parsed['path'] ]
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
