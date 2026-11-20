<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc;

use AppBundle\Integration\Rdc\Enum\EventCode;
use AppBundle\Integration\Rdc\Enum\IncidentCode;
use AppBundle\Integration\Rdc\Enum\ReasonCode;

/**
 * How the BOL reads the failure reason of an incident:
 * the code says what happened, the reason code says why.
 */
final readonly class RdcIncidentReason
{
    private function __construct(
        public EventCode|IncidentCode $code,
        public ?ReasonCode $reasonCode,
    ) {}

    public static function fromFailureReasonCode(?string $failureReasonCode, bool $isPickup): self
    {
        $notDone = $isPickup ? EventCode::NOT_COLLECTED : EventCode::NOT_DELIVERED;

        return match ($failureReasonCode) {
            'DAMAGED' => new self(IncidentCode::ITEM_DAMAGED, ReasonCode::TRANSPORT_DAMAGE),
            'PACKAGING_ISSUE' => new self(IncidentCode::CONTAINER_DAMAGED, ReasonCode::MATERIAL_ISSUE),
            'LOST', 'INCORRECT_ITEM', 'REFUSED', 'INVENTORY_ISSUE' => new self(IncidentCode::GOODS_ISSUE, ReasonCode::OTHER),
            'ITEM_MISSING' => new self(IncidentCode::ITEM_MISSING, ReasonCode::OTHER),
            'LATE' => new self($isPickup ? IncidentCode::LATE_PICKUP : IncidentCode::LATE_DELIVERY, null),
            'NOT_AT_HOME' => new self(IncidentCode::RECIPIENT_ABSENT, null),
            'CLOSED_STORE', 'WRONG_ADDR', 'ADDRESS_UNREACHABLE' => new self(IncidentCode::ACCESS_IMPOSSIBLE, ReasonCode::OTHER),
            'ROUTE_ISSUE' => new self(IncidentCode::ACCESS_IMPOSSIBLE, ReasonCode::TRAFIC),
            'TRAFFIC' => new self($notDone, ReasonCode::TRAFIC),
            'WEATHER' => new self($notDone, ReasonCode::WEATHER),
            'VEHICLE_BREAKDOWN' => new self($notDone, ReasonCode::MECHANICAL),
            'CUSTOMS_ISSUE' => new self(IncidentCode::SERVICE_PENDING, ReasonCode::MISSING_DOCUMENT),
            default => new self($notDone, ReasonCode::OTHER),
        };
    }
}
