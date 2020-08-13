<?php
declare(strict_types=1);

namespace AppBundle\Entity;

use Sylius\Component\Customer\Model\CustomerGroup;

class Organization
{
    private $id;
    private $name;
    private OrganizationConfig $config;

    /**
     * Organization constructor.
     *
     * @param $group
     * @param $config
     */
    public function __construct(string $name, OrganizationConfig $config)
    {
        $this->name = $name;
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
    public function getName()
    {
        return $this->name;
    }
}
