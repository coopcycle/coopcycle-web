<?php

namespace AppBundle\Transporter\Proximus;

use League\Flysystem\Filesystem;
use AppBundle\Transporter\TransporterHelpers;
use AppBundle\Transporter\TransporterImporterInterface;

class ProximusImporter implements TransporterImporterInterface
{

    private Filesystem $fs;

    /**
     * @param array<string, mixed> $sync_config
     */
    public function __construct(
        array $sync_config
    )
    {
        if (!isset($sync_config['proximus_sync_uri'])) {
            throw new \Exception('Missing Proximus sync URI');
        }

        $this->fs = TransporterHelpers::parseSyncOptions($sync_config['proximus_sync_uri']);

    }

    public function pull(array $options = []): array
    {
        $csv = $this->fs->read(sprintf('/%s.csv', date('Y-m-d', time())));
        return [$csv];
    }

    public function flush(bool $dry_run = false): void
    {
        $this->fs->delete(sprintf('/%s.csv', date('Y-m-d', time())));
    }

}
