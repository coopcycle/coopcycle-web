<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Base\CreativeWork;
use AppBundle\Entity\Menu\Section;
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
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Menu\Section", mappedBy="menu", cascade={"all"})
     * @Groups({"restaurant"})
     */
    private $sections;

    public function __construct()
    {
        $this->sections = new ArrayCollection();
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

    public function getSections()
    {
        return $this->sections;
    }

    public function addSection($menuSection)
    {
        if ($menuSection instanceof MenuSection) {
            $section = new Section();
            $section->setMenu($this);
            $section->setMenuSection($menuSection);

            $this->sections->add($section);
        }

        if ($menuSection instanceof Section) {
            $menuSection->setMenu($this);
            $this->sections->add($menuSection);
        }
    }

    public function getAllItems()
    {
        $items = new ArrayCollection();

        foreach ($this->sections as $section) {
            foreach ($section->getItems() as $item) {
                $items->add($item);
            }
        }

        return $items;
    }
}
