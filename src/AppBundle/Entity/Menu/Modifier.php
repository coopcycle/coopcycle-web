<?php


namespace AppBundle\Entity\Menu;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\MenuItem as BaseMenuItem;
use Doctrine\ORM\Mapping as ORM;


/**
 * @ORM\Entity
 * @ORM\Table(name="modifier")
 * @ApiResource(
 *  shortName="Modifier",
 *  iri="http://schema.org/MenuItem",
 *  collectionOperations={})
 */
class Modifier extends BaseMenuItem
{

    /**
     * @var MenuItemModifier
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Menu\MenuItemModifier", inversedBy="modifierChoices", cascade={"persist"})
     *
     */
    private $menuItemModifier;

    /**
     * @return mixed
     */
    public function getMenuItemModifier()
    {
        return $this->menuItemModifier;
    }

    /**
     * @param mixed $menuItemModifier
     */
    public function setMenuItemModifier($menuItemModifier)
    {
        $this->menuItemModifier = $menuItemModifier;
    }


}