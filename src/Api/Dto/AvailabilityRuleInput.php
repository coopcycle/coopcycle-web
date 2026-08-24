<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\AvailabilityRule;
use AppBundle\Entity\User;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class AvailabilityRuleInput
{
    /**
     * Only honored when the requester is a dispatcher (creating a rule on
     * behalf of an employee); couriers are always forced to themselves.
     */
    #[Groups(['availability_rule_create'])]
    public ?User $user = null;

    #[Groups(['availability_rule_create'])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [AvailabilityRule::TYPE_AVAILABLE, AvailabilityRule::TYPE_UNAVAILABLE])]
    public ?string $type = null;

    /**
     * ISO day of week, 1 (Monday) to 7 (Sunday).
     */
    #[Groups(['availability_rule_create'])]
    #[Assert\NotBlank]
    #[Assert\Range(min: 1, max: 7)]
    public ?int $dayOfWeek = null;

    /**
     * "HH:MM", e.g. "13:00".
     */
    #[Groups(['availability_rule_create'])]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/', message: 'This is not a valid time (expected HH:MM).')]
    public ?string $startTime = null;

    #[Groups(['availability_rule_create'])]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/', message: 'This is not a valid time (expected HH:MM).')]
    #[Assert\GreaterThan(propertyPath: 'startTime')]
    public ?string $endTime = null;

    #[Groups(['availability_rule_create'])]
    #[Assert\Length(max: 65535)]
    public ?string $comment = null;
}
