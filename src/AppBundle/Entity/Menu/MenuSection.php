<?php

namespace AppBundle\Entity\Menu;

use AppBundle\Entity\Base\CreativeWork;
use AppBundle\Entity\Menu;
use AppBundle\Entity\Base\MenuItem;
use AppBundle\Entity\Model\Name\MethodsTrait as NameMethods;
use Doctrine\ORM\Mapping as ORM;
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
 * @ORM\Entity
 * @ORM\Table(name="menu_section")
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
    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string The name of the section
     *
     * @ORM\Column(nullable=true)
     * @ApiProperty(iri="http://schema.org/name")
     * @Groups({"restaurant"})
     */
    protected $name;

    use NameMethods;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Menu", inversedBy="sections", cascade={"all"})
     * @ORM\JoinColumn(name="menu_id", referencedColumnName="id")
     */
    private $menu;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Menu\MenuItem", mappedBy="section", cascade={"all"})
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
