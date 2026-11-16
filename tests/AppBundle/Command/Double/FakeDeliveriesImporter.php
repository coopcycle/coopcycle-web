<?php

declare(strict_types=1);

namespace Tests\AppBundle\Command\Double;

use AppBundle\Transporter\TransporterImporterInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem('fake')]
class FakeDeliveriesImporter implements TransporterImporterInterface
{
    const VALID_FILE = <<<'CSV'
    ADDRESS;COMMENT
    1 rue de la Paix, Paris;"FAKE COMMENT
    ON TWO LINES"
    2 rue de Rivoli, Paris;
    CSV;

    /** @var array<array-key,string> What pull() returns, tests may change it */
    public array $files = ['valid.csv' => self::VALID_FILE];

    /** @var array<int,array{dry_run: bool, rejected: array<array-key,string>}> */
    public array $flushCalls = [];

    public function pull(array $options = []): array
    {
        return $this->files;
    }

    public function flush(bool $dry_run = false, array $rejected = []): void
    {
        $this->flushCalls[] = ['dry_run' => $dry_run, 'rejected' => $rejected];
    }
}
