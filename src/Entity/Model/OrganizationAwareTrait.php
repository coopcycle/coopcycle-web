<?php

namespace AppBundle\Entity\Model;

use AppBundle\Entity\Organization;

trait OrganizationAwareTrait
{
    protected $organization;

    public function setOrganization(?Organization $organization)
    {
        $this->organization = $organization;

        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }
}
