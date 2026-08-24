<?php

namespace Tests\AppBundle\Integration\Rdc\DTO;

use AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest;
use PHPUnit\Framework\TestCase;

class RdcApiServiceRequestTest extends TestCase
{
    public function testGetPickupTimeSlotAnchorsInstantToUtc(): void
    {
        $request = RdcApiServiceRequest::parse([
            'startLocation' => [
                'requestedStartTimeRange' => [
                    'earliestDateTime' => '2024-06-01T08:00:00Z',
                    'latestDateTime' => '2024-06-01T10:00:00Z',
                ],
            ],
        ]);

        $slot = $request->getPickupTimeSlot();

        $this->assertNotNull($slot->start);
        $this->assertNotNull($slot->end);
        $this->assertSame('UTC', $slot->start->getTimezone()->getName());
        $this->assertSame('UTC', $slot->end->getTimezone()->getName());
        $this->assertSame('2024-06-01T08:00:00+00:00', $slot->start->format('c'));
        $this->assertSame('2024-06-01T10:00:00+00:00', $slot->end->format('c'));
    }

    public function testGetDropoffTimeSlotAnchorsInstantToUtc(): void
    {
        $request = RdcApiServiceRequest::parse([
            'endLocation' => [
                'requestedEndTimeRange' => [
                    'earliestDateTime' => '2024-06-01T14:00:00Z',
                    'latestDateTime' => '2024-06-01T16:30:00Z',
                ],
            ],
        ]);

        $slot = $request->getDropoffTimeSlot();

        $this->assertNotNull($slot->start);
        $this->assertNotNull($slot->end);
        $this->assertSame('UTC', $slot->start->getTimezone()->getName());
        $this->assertSame('2024-06-01T14:00:00+00:00', $slot->start->format('c'));
        $this->assertSame('2024-06-01T16:30:00+00:00', $slot->end->format('c'));
    }

    public function testTimeSlotReturnsNullWhenRangeMissing(): void
    {
        $request = RdcApiServiceRequest::parse([]);

        $pickup = $request->getPickupTimeSlot();
        $dropoff = $request->getDropoffTimeSlot();

        $this->assertNull($pickup->start);
        $this->assertNull($pickup->end);
        $this->assertNull($dropoff->start);
        $this->assertNull($dropoff->end);
    }
}
