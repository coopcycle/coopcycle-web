<?php
namespace AppBundle\Integration\Standtrack;

use DateTime;
use DateTimeInterface;


class DeliveryEvent
{

    /**
     * @param array<int,mixed> $iubCodes
     */
    public function __construct(
        private readonly EventType $eventType,
        private readonly string $shipmentNumber,
        private readonly array $iubCodes,
        private readonly ?string $receiverGln = null,
        private readonly ?\DateTimeInterface $eventDateTime = new DateTime(),
        private readonly ?IncidentType $incidentType = null
    ) { }

    /**
     * @return array<string,mixed>
     */
    public function toArray(string $senderGln): array
    {
        $payload = [
            'M03001' => strval($this->eventType->value), // Standtrack expects a string
            'M03002' => $this->shipmentNumber,
            'M03003' => $senderGln,
            'M03008' => $this->eventDateTime->format('Ymd'),
            'M03009' => $this->eventDateTime->format('Hi'),
            'iuBs' => array_map(
                fn(string $iubCode) => [
                    'M03010' => strval($iubCode), // Standtrack expects a string
                    'M03011' => 'OK'
                ],
                $this->iubCodes
            )
        ];

        if ($this->receiverGln) {
            $payload['M03004'] = $this->receiverGln;
        }

        // Add incident type if this is an incident event
        if ($this->eventType === EventType::INCIDENT && $this->incidentType !== null) {
            $payload['M03012'] = $this->incidentType->value;
        }

        return $payload;
    }
}
