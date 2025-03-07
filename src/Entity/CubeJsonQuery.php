<?php

namespace AppBundle\Entity;

class CubeJsonQuery
{
    private $id;

    private string $name;

    private array $query = [];

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return array
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * @param array $query
     *
     * @return self
     */
    public function setQuery(array $query)
    {
        $this->query = $query;

        return $this;
    }
}

