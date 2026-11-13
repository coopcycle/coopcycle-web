<?php

declare(strict_types=1);

namespace Tests\AppBundle\Command\Double;

use AppBundle\Transporter\TransporterImporterInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem('fake')]
class FakeDeliveriesImporter implements TransporterImporterInterface
{
    /** @var array<int,bool> */
    public array $flushCalls = [];

    public function pull(array $options = []): array
    {
        return [<<<'CSV'
        ADDRESS;COMMENT
        1 rue de la Paix, Paris;"FAKE COMMENT
        ON TWO LINES"
        2 rue de Rivoli, Paris;
        CSV];
    }

    public function flush(bool $dry_run = false): void
    {
        $this->flushCalls[] = $dry_run;
    }
}
