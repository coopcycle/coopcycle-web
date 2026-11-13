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
     * @return array<string>
     */
    public function pull(array $options = []): array;
    public function flush(bool $dry_run = false): void;
}
