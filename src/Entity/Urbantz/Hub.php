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

    /**
     * @return Store
     */
    public function getStore(): Store
    {
        return $this->store;
    }

    /**
     * @param Store $store
     *
     * @return self
     */
    public function setStore(Store $store)
    {
        $this->store = $store;

        return $this;
    }

    /**
     * @return string
     */
    public function getHub(): string
    {
        return $this->hub;
    }

    /**
     * @param string $hub
     *
     * @return self
     */
    public function setHub(string $hub)
    {
        $this->hub = $hub;

        return $this;
    }
}
