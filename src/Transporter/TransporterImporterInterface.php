<?php

namespace AppBundle\Transporter;

/**
 * Importers are found by `coopcycle:deliveries:import` through this tag
 * (added in `Kernel::build()`),
 * each one names itself with `#[AsTaggedItem('name')]` (the `-i` option).
 */
interface TransporterImporterInterface {

    const TAG = 'coopcycle.deliveries_importer';

    /**
     * @param array<int,mixed> $options
     * @return array<array-key,string> Contents, keyed by an id of their source (given back to flush())
     */
    public function pull(array $options = []): array;

    /**
     * Called once the pulled contents were queued.
     * @param array<array-key,string> $rejected Contents that couldn't be transformed: reasons, keyed like pull()
     */
    public function flush(bool $dry_run = false, array $rejected = []): void;
}
