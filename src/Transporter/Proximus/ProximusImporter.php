<?php

namespace AppBundle\Transporter\Proximus;

use League\Flysystem\Filesystem;
use AppBundle\Transporter\TransporterHelpers;
use AppBundle\Transporter\TransporterImporterInterface;

class ProximusImporter implements TransporterImporterInterface
{

    private Filesystem $fs;

    public function __construct(string $sync_uri)
    {
        $this->fs = TransporterHelpers::parseSyncOptions($sync_uri);
    }

    public function pull(array $options = []): array
    {
        $csv = $this->fs->read(sprintf('/rayon9_%s.csv', date('Ymd', time())));
        return [$csv];
    }

    public function flush(bool $dry_run = false): void
    {
        if ($dry_run) {
            return;
        }
        $this->fs->delete(sprintf('/rayon9_%s.csv', date('Ymd', time())));
    }

}
