<?php

declare(strict_types=1);

namespace App\Entity\User;

use Nucleos\UserBundle\Model\Group as BaseGroup;

class Group extends BaseGroup
{
    public function setId(string $id): void
    {
        $this->id = $id;
    }
}
