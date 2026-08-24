<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\AvailabilityRule;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The owning user can never be changed after creation — delete and recreate
 * to reassign a rule to a different employee.
 */
final class AvailabilityRuleUpdateInput
{
    #[Groups(['availability_rule_create'])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [AvailabilityRule::TYPE_AVAILABLE, AvailabilityRule::TYPE_UNAVAILABLE])]
    public ?string $type = null;

    #[Groups(['availability_rule_create'])]
    #[Assert\NotBlank]
    #[Assert\Range(min: 1, max: 7)]
    public ?int $dayOfWeek = null;

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
