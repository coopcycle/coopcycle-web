<?php
declare(strict_types=1);

namespace AppBundle\Entity;

use Sylius\Component\Customer\Model\CustomerGroup;

class Organization
{
    private $id;
    private $group;
    private OrganizationConfig $config;

    /**
     * Organization constructor.
     * @param $id
     * @param $group
     * @param $config
     */
    public function __construct(CustomerGroup $group, OrganizationConfig $config)
    {
        $this->group = $group;
        $this->config = $config;
        $this->config->setOrganization($this);
    }

    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getGroup()
    {
        return $this->group;
    }
}
