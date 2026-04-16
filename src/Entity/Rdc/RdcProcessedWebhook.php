<?php

declare(strict_types=1);

namespace AppBundle\Entity\Rdc;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\Timestampable;

/**
 * Tracks processed webhooks for idempotency.
 *
 * @ORM\Entity
 * @ORM\Table(name="rdc_processed_webhooks")
 * @ORM\UniqueConstraint(name="uniq_lo_uri", columns={"lo_uri"})
 */
class RdcProcessedWebhook
{
    use Timestampable;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private string $loUri;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private string $eventType;

    public function __construct(string $loUri, string $eventType)
    {
        $this->loUri = $loUri;
        $this->eventType = $eventType;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLoUri(): string
    {
        return $this->loUri;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }
}
