<?php

namespace AppBundle\Entity\Sylius;

abstract class FrozenOrderFooterItem
{
    const SECTION_PRODUCTS = 'products';
    const SECTION_SERVICES = 'services';
    const SECTION_TOTALS = 'totals';

    /** @var int */
    protected $id;

    /** @var string */
    protected $section = self::SECTION_TOTALS;

    /** @var FrozenOrder */
    protected $parent;

    /** @var string */
    protected $name;

    /** @var int */
    protected $total = 0;

    /** @var int */
    protected $position;

    public function __construct($name = null, $total = 0, $section = self::SECTION_TOTALS)
    {
        $this->name = $name;
        $this->total = $total;
        $this->section = $section;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param mixed $parent
     *
     * @return self
     */
    public function setParent($parent)
    {
        $this->parent = $parent;

        return $this;
    }

    public function getSection(): string
    {
        return $this->section ?? self::SECTION_TOTALS;
    }

    /**
     * @return self
     */
    public function setSection(string $section)
    {
        $this->section = $section;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * @param mixed $total
     *
     * @return self
     */
    public function setTotal($total)
    {
        $this->total = $total;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @param mixed $position
     *
     * @return self
     */
    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }
}
