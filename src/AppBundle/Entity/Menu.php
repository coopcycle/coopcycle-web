<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Base\CreativeWork;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A structured representation of food or drink items available from a FoodEstablishment.
 *
 * @see http://schema.org/Menu Documentation on Schema.org
 *
 * @ORM\Entity
 * @ApiResource(iri="http://schema.org/Menu",
 *   attributes={
 *     "normalization_context"={"groups"={"restaurant"}}
 *   },
 *   collectionOperations={},
 *   itemOperations={
 *     "get"={"method"="GET"}
 *   }
 * )
 */
class Menu extends CreativeWork
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
     * @ORM\ManyToMany(targetEntity="MenuSection", cascade={"all"})
     * @ORM\JoinTable(inverseJoinColumns={@ORM\JoinColumn()})
     * @ORM\OrderBy({"name"="ASC"})
     * @ApiProperty(iri="https://schema.org/MenuSection")
     * @Groups({"restaurant"})
     */
    private $hasMenuSection;

    public function __construct()
    {
        $this->hasMenuSection = new ArrayCollection();
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

    public function getHasMenuSection()
    {
        return $this->getSections();
    }

    public function getSections()
    {
        return $this->hasMenuSection;
    }

    public function getAllItems()
    {
        $items = new ArrayCollection();

        foreach ($this->hasMenuSection as $section) {
            foreach ($section->getItems() as $item) {
                $items->add($item);
            }
        }

        return $items;
    }

    public function addSection(MenuSection $menuSection)
    {
        $this->hasMenuSection->add($menuSection);
    }
}
