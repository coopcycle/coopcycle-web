<?php

declare(strict_types=1);

namespace App\Entity\User;

use Nucleos\UserBundle\Model\User as BaseUser;

/**
 * @phpstan-extends User<\Nucleos\UserBundle\Model\GroupInterface>
 */
class User extends BaseUser
{
    public function setId(string $id): void
    {
        $this->id = $id;
    }
}
