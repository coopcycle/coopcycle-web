<?php

namespace AppBundle\Transporter;

interface TransporterImporterInterface {
    /**
     * @param array<int,mixed> $options
     * @return array<string>
     */
    public function pull(array $options = []): array;
    public function flush(bool $dry_run = false): void;
}
