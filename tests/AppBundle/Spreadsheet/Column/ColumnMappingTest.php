<?php

namespace Tests\AppBundle\Spreadsheet\Column;

use AppBundle\Spreadsheet\Column\ColumnMapping;
use PHPUnit\Framework\TestCase;

class ColumnMappingTest extends TestCase
{
    private const KEYS = ['pickup.address', 'dropoff.address', 'weight'];

    public function testIgnoredColumnsAreLeftOut()
    {
        $mapping = ColumnMapping::fromArray([
            0 => 'pickup.address',
            1 => '',
            2 => null,
            3 => 'weight',
        ]);

        $this->assertSame([0 => 'pickup.address', 3 => 'weight'], $mapping->toArray());
        $this->assertFalse($mapping->isEmpty());
    }

    public function testMappingSubmittedWithStringIndexes()
    {
        $mapping = ColumnMapping::fromArray(['2' => 'weight', '0' => 'pickup.address']);

        $this->assertSame([0 => 'pickup.address', 2 => 'weight'], $mapping->toArray());
    }

    public function testEmptyMapping()
    {
        $this->assertTrue(ColumnMapping::fromArray([])->isEmpty());
        $this->assertTrue(ColumnMapping::fromArray([0 => '', 1 => null])->isEmpty());
    }

    public function testReadsColumnsByPositionWhateverTheHeaderSays()
    {
        // Two columns with the same header, which reading by name could not tell apart
        $mapping = ColumnMapping::fromArray([0 => 'pickup.address', 1 => 'dropoff.address']);

        $record = $mapping->apply(['here', 'there', 'ignored'], self::KEYS);

        $this->assertSame('here', $record['pickup.address']);
        $this->assertSame('there', $record['dropoff.address']);
    }

    public function testUnmappedColumnsAreDropped()
    {
        $mapping = ColumnMapping::fromArray([1 => 'weight']);

        $record = $mapping->apply(['dropped', '12.5', 'dropped too'], self::KEYS);

        $this->assertSame('12.5', $record['weight']);
        $this->assertSame(['pickup.address', 'dropoff.address', 'weight'], array_keys($record));
    }

    /**
     * The parsers read some of their columns without checking they are there first
     */
    public function testEveryFieldIsInTheRecordEvenWhenTheFileHasNone()
    {
        $record = ColumnMapping::fromArray([0 => 'weight'])->apply(['12.5'], self::KEYS);

        $this->assertArrayHasKey('pickup.address', $record);
        $this->assertNull($record['pickup.address']);
        $this->assertArrayHasKey('dropoff.address', $record);
        $this->assertNull($record['dropoff.address']);
    }

    public function testRowShorterThanTheMapping()
    {
        $mapping = ColumnMapping::fromArray([0 => 'pickup.address', 2 => 'weight']);

        $record = $mapping->apply(['here'], self::KEYS);

        $this->assertSame('here', $record['pickup.address']);
        $this->assertNull($record['weight']);
    }

    public function testRowLongerThanTheMapping()
    {
        $mapping = ColumnMapping::fromArray([0 => 'pickup.address']);

        $record = $mapping->apply(['here', 'and', 'then', 'some'], self::KEYS);

        $this->assertSame('here', $record['pickup.address']);
        $this->assertSame(self::KEYS, array_keys($record));
    }

    public function testDuplicates()
    {
        $mapping = ColumnMapping::fromArray([
            0 => 'pickup.address',
            1 => 'pickup.address',
            2 => 'weight',
        ]);

        $this->assertSame(['pickup.address'], $mapping->duplicates());
        $this->assertSame([], ColumnMapping::fromArray([0 => 'weight'])->duplicates());
    }

    public function testKeyAt()
    {
        $mapping = ColumnMapping::fromArray([1 => 'weight']);

        $this->assertSame('weight', $mapping->keyAt(1));
        $this->assertNull($mapping->keyAt(0));
    }
}
