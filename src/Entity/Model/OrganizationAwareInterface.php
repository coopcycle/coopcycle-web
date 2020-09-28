<?php

namespace AppBundle\Entity\Model;

use AppBundle\Entity\Organization;

interface OrganizationAwareInterface
{
    /**
     * @param Organization|null $organization
     */
    public function setOrganization(?Organization $organization);

    /**
     * @return Organization|null
     */
    public function getOrganization(): ?Organization;
}
