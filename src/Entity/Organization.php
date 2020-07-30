<?php
declare(strict_types=1);

namespace AppBundle\Entity;

class Organization
{
    private $id;
    private $group;
    private $config;

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
