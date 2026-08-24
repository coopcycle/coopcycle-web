<?php

declare(strict_types=1);

namespace AppBundle\Message;

final class IncidentRdcMessage
{
    public function __construct(public readonly int $incidentId)
    {
    }
}