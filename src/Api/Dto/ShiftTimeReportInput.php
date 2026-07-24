<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class ShiftTimeReportInput
{
    /**
     * User IRI to report for. Dispatchers only — employees always report for
     * themselves and must omit this (or pass their own IRI).
     */
    #[Groups(['shift_time_report'])]
    public ?string $user = null;

    /**
     * Actual worked interval, wall-clock local time (like shift times).
     */
    #[Groups(['shift_time_report'])]
    #[Assert\DateTime(format: 'Y-m-d\TH:i:s')]
    public ?string $startsAt = null;

    #[Groups(['shift_time_report'])]
    #[Assert\DateTime(format: 'Y-m-d\TH:i:s')]
    public ?string $endsAt = null;

    #[Groups(['shift_time_report'])]
    #[Assert\Range(min: 0)]
    public int $breakMinutes = 0;

    #[Groups(['shift_time_report'])]
    #[Assert\Length(max: 65535)]
    public ?string $comment = null;

    /**
     * True to delete the report and revert to "worked as planned".
     */
    #[Groups(['shift_time_report'])]
    public bool $clear = false;
}
