<?php

namespace AppBundle\Entity\Urbantz;

use AppBundle\Entity\Store;

class Hub
{
    private $id;
    private $store;
    private $hub;

    public function getId()
    {
        return $this->id;
    }

    public function getStore(): Store
    {
        return $this->store;
    }

    /**
     * @return self
     */
    public function setStore(Store $store)
    {
        $this->store = $store;

        return $this;
    }

    public function getHub(): string
    {
        return $this->hub;
    }

    /**
     * @return self
     */
    public function setHub(string $hub)
    {
        $this->hub = $hub;

        return $this;
    }
}
