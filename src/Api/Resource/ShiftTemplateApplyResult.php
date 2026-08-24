<?php

namespace AppBundle\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Result of applying a ShiftTemplate to a target week (POST
 * /shift_templates/{id}/apply, declared on ShiftTemplate's own operations so
 * {id} resolves against it): creates real Shift entities for that week — see
 * ShiftManager::applyTemplate().
 */
final class ShiftTemplateApplyResult
{
    #[Groups(['shift_template_apply'])]
    public int $created;

    public function __construct(int $created)
    {
        $this->created = $created;
    }
}
