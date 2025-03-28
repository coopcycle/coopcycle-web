<?php

namespace AppBundle\Entity\Model;

use AppBundle\Entity\Organization;

interface OrganizationAwareInterface
{
    public function setOrganization(?Organization $organization);

    public function getOrganization(): ?Organization;
}
