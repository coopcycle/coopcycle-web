<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class ShiftTemplateApplyInput
{
    /**
     * Any date within the target week; the applied shifts align to the Monday.
     */
    #[Groups(['shift_template_apply'])]
    #[Assert\NotBlank]
    #[Assert\Date]
    public ?string $targetWeek = null;

    /**
     * Whether to also (re-)assign the users snapshotted in the template, for
     * the shift lines that have any. Ignored for lines with no snapshot.
     */
    #[Groups(['shift_template_apply'])]
    public bool $includeAssignees = false;
}
