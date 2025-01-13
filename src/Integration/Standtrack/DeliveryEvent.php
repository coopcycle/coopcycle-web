<?php
namespace AppBundle\Integration\Standtrack;

use DateTimeInterface;


class DeliveryEvent
{
    private EventType $eventType;
    private string $shipmentNumber;
    private array $iubCodes;
    private ?string $receiverGln;
    private ?\DateTimeInterface $eventDateTime;
    private ?IncidentType $incidentType;

    /**
     * @param array<int,mixed> $iubCodes
     */
    public function __construct(
        EventType $eventType,
        string $shipmentNumber,
        array $iubCodes,
        ?string $receiverGln = null,
        ?\DateTimeInterface $eventDateTime = null,
        ?IncidentType $incidentType = null
    ) {
        $this->eventType = $eventType;
        $this->shipmentNumber = $shipmentNumber;
        $this->iubCodes = $iubCodes;
        $this->receiverGln = $receiverGln;
        $this->eventDateTime = $eventDateTime ?? new \DateTime();
        $this->incidentType = $incidentType;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(string $senderGln): array
    {
        $payload = [
            'M03001' => $this->eventType,
            'M03002' => $this->shipmentNumber,
            'M03003' => $senderGln,
            'M03008' => $this->eventDateTime->format('Ymd'),
            'M03009' => $this->eventDateTime->format('Hi'),
            'IUB\'s' => array_map(
                fn(string $iubCode) => [
                    'M03010' => $iubCode,
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
