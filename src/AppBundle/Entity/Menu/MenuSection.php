<?php

namespace AppBundle\Entity\Menu;

use AppBundle\Entity\Base\CreativeWork;
use AppBundle\Entity\Menu;
use AppBundle\Entity\Base\MenuItem;
use AppBundle\Entity\Model\Name\MethodsTrait as NameMethods;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * A sub-grouping of food or drink items in a menu. E.g. courses (such as 'Dinner', 'Breakfast', etc.),
 * specific type of dishes (such as 'Meat', 'Vegan', 'Drinks', etc.), or some other classification made by the menu provider.
 *
 * @see http://schema.org/MenuSection Documentation on Schema.org
 *
 * @ApiResource(
 *  shortName="MenuSection",
 *  iri="http://schema.org/MenuSection",
 *  attributes={
 *    "normalization_context"={"groups"={"restaurant"}}
 *  },
 *  collectionOperations={},
 *  itemOperations={
 *    "get"={"method"="GET"}
 *  })
 */
class MenuSection
{
    use NameMethods;

    /**
     * @var int
     */
    private $id;

    /**
     * @var string The name of the section
     *
     * @ApiProperty(iri="http://schema.org/name")
     * @Groups({"restaurant"})
     */
    protected $name;

    private $menu;

    /**
     * @ApiProperty(iri="https://schema.org/MenuItem")
     * @Groups({"restaurant"})
     */
    private $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    /**
     * Gets id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function getItems()
    {
        return $this->items;
    }

    /**
     * @param mixed $items
     */
    public function setItems($items)
    {
        $this->items = $items;
    }

    public function addItem(MenuItem $item)
    {
        $item->setSection($this);
        $this->items->add($item);
    }

    public function getMenu()
    {
        return $this->menu;
    }

    public function setMenu(Menu $menu = null)
    {
        $this->menu = $menu;

        return $this;
    }
}
