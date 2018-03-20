<?php

namespace AppBundle\Entity\Menu;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Base\MenuItem as BaseMenuItem;

/**
 * @ApiResource(
 *  shortName="Modifier",
 *  iri="http://schema.org/MenuItem",
 *  collectionOperations={})
 */
class Modifier extends BaseMenuItem
{

    /**
     * @var MenuItemModifier
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
