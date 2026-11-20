<?php

declare(strict_types=1);

namespace Tests\AppBundle\Integration\Rdc;

use AppBundle\Integration\Rdc\RdcIncidentReason;
use PHPUnit\Framework\TestCase;

class RdcIncidentReasonTest extends TestCase
{
    public static function failureReasons(): array
    {
        return [
            'damaged' => ['DAMAGED', false, 'ITEM_DAMAGED', 'TRANSPORT_DAMAGE'],
            'packaging' => ['PACKAGING_ISSUE', false, 'CONTAINER_DAMAGED', 'MATERIAL_ISSUE'],
            'lost' => ['LOST', false, 'GOODS_ISSUE', 'OTHER'],
            'refused' => ['REFUSED', false, 'GOODS_ISSUE', 'OTHER'],
            'item missing' => ['ITEM_MISSING', false, 'ITEM_MISSING', 'OTHER'],
            'late pickup' => ['LATE', true, 'LATE_PICKUP', null],
            'late delivery' => ['LATE', false, 'LATE_DELIVERY', null],
            'recipient absent' => ['NOT_AT_HOME', false, 'RECIPIENT_ABSENT', null],
            'closed store' => ['CLOSED_STORE', true, 'ACCESS_IMPOSSIBLE', 'OTHER'],
            'route issue' => ['ROUTE_ISSUE', false, 'ACCESS_IMPOSSIBLE', 'TRAFIC'],
            'traffic on a pickup' => ['TRAFFIC', true, 'NOT_COLLECTED', 'TRAFIC'],
            'weather on a dropoff' => ['WEATHER', false, 'NOT_DELIVERED', 'WEATHER'],
            'breakdown' => ['VEHICLE_BREAKDOWN', false, 'NOT_DELIVERED', 'MECHANICAL'],
            'customs' => ['CUSTOMS_ISSUE', false, 'SERVICE_PENDING', 'MISSING_DOCUMENT'],
            'unmapped reason' => ['HOLIDAY', true, 'NOT_COLLECTED', 'OTHER'],
            'no reason' => [null, false, 'NOT_DELIVERED', 'OTHER'],
        ];
    }

    /**
     * @dataProvider failureReasons
     */
    public function testTellsTheBolWhatHappenedAndWhy(?string $failureReasonCode, bool $isPickup, string $code, ?string $reasonCode): void
    {
        $reason = RdcIncidentReason::fromFailureReasonCode($failureReasonCode, $isPickup);

        $this->assertSame($code, $reason->code->value);
        $this->assertSame($reasonCode, $reason->reasonCode?->value);
    }
}
