<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use AppBundle\Api\State\ShiftComplianceProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Legal-constraint check of a week's shift assignments (?week=, any date in
 * the target week). Returns the violations of the configured constraint
 * template so the planning UI can warn the dispatcher — violations never
 * block saving or publishing a schedule.
 */
#[ApiResource(
    shortName: 'ShiftCompliance',
    operations: [
        new Get(
            uriTemplate: '/shifts/compliance',
            provider: ShiftComplianceProvider::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')'
        ),
    ],
    normalizationContext: ['groups' => ['shift_compliance']]
)]
final class ShiftCompliance
{
    #[Groups(['shift_compliance'])]
    public string $week;

    /**
     * The active template id, or null when legal constraints are disabled.
     */
    #[Groups(['shift_compliance'])]
    public ?string $template;

    /**
     * @var array<int, array<string, mixed>> {username, rule, limit, actual, ...context}
     */
    #[Groups(['shift_compliance'])]
    public array $violations;

    public function __construct(string $week, ?string $template, array $violations)
    {
        $this->week = $week;
        $this->template = $template;
        $this->violations = $violations;
    }
}
