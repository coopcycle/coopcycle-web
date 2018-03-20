<?php

namespace AppBundle\Entity\Menu;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Base\MenuItem as BaseMenuItem;
use AppBundle\Entity\Menu\MenuItemModifier;
use AppBundle\Entity\Restaurant;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *  shortName="MenuItem",
 *  iri="http://schema.org/MenuItem",
 *  collectionOperations={},
 *  itemOperations={
 *    "get"={"method"="GET"}
 *  })
 */
class MenuItem extends BaseMenuItem
{
    private $section;

    /**
     * @Groups({"restaurant"})
     */
    protected $modifiers;

    private $deletedAt;

    /**
     * @Groups({"restaurant"})
     */
    private $isAvailable = true;

    public function __construct()
    {
        $this->modifiers = new ArrayCollection();
    }

    /**
     * @return Restaurant
     */
    public function getRestaurant() {
        return $this->section->getMenu()->getRestaurant();
    }

    /**
     * @return MenuSection
     */
    public function getSection()
    {
        return $this->section;
    }

    public function setSection(MenuSection $section = null)
    {
        $this->section = $section;

        return $this;
    }

    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
    }

    /**
     * @return mixed
     */
    public function getIsAvailable()
    {
        return $this->isAvailable;
    }

    /**
     * @param mixed $isUnavailable
     */
    public function setIsAvailable($isUnavailable)
    {
        $this->isAvailable = $isUnavailable;
    }

    /**
     * @return ArrayCollection|MenuItemModifier[]
     */
    public function getModifiers()
    {
        return $this->modifiers;
    }

    /**
     * @param mixed $modifiers
     */
    public function setModifiers($modifiers)
    {
        $this->modifiers = $modifiers;
    }

    public function addModifier(MenuItemModifier $modifier) {
        $modifier->setMenuItem($this);
        $this->modifiers->add($modifier);
    }

}
