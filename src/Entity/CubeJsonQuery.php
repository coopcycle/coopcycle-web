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
     * @return self
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * @return self
     */
    public function setQuery(array $query)
    {
        $this->query = $query;

        return $this;
    }
}

