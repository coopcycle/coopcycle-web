<?php

namespace AppBundle\Entity\Menu;

use AppBundle\Entity\Base\CreativeWork;
use AppBundle\Entity\Menu;
use AppBundle\Entity\Base\MenuItem;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
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
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Menu", inversedBy="sections")
     * @ORM\JoinColumn(name="menu_id", referencedColumnName="id")
     */
    private $menu;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\MenuSection")
     * @ORM\JoinColumn(name="menu_section_id", referencedColumnName="id")
     * @ApiProperty(iri="https://schema.org/MenuSection")
     * @Groups({"restaurant"})
     */
    private $menuSection;

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

    public function getName()
    {
        return isset($this->menuSection) ? $this->menuSection->getName() : null;
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

    public function getMenuSection()
    {
        return $this->menuSection;
    }

    public function setMenuSection($menuSection)
    {
        $this->menuSection = $menuSection;

        return $this;
    }
}
