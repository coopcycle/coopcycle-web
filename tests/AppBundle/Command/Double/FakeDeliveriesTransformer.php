<?php

declare(strict_types=1);

namespace Tests\AppBundle\Command\Double;

use AppBundle\Transporter\TransporterTransformerInterface;
use League\Csv\Reader;
use League\Csv\Writer;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem('fake')]
class FakeDeliveriesTransformer implements TransporterTransformerInterface
{
    public function transform($data): string
    {
        $reader = Reader::fromString($data)->setDelimiter(';')->setHeaderOffset(0);

        $output = Writer::fromString();
        $output->insertOne(['dropoff.address', 'dropoff.comments']);
        foreach ($reader->getRecords() as $record) {
            $output->insertOne([$record['ADDRESS'], $record['COMMENT']]);
        }

        return $output->toString();
    }
}
