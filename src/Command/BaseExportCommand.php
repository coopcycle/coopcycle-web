<?php

namespace AppBundle\Command;

use Aws\S3\S3Client;
use DateTime;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputOption;

abstract class BaseExportCommand extends Command {

    use LockableTrait;

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
        $filesystem->write($options['key'], $contents);
    }


}
