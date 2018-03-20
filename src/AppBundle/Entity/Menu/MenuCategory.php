<?php

namespace AppBundle\Entity\Menu;

use AppBundle\Entity\Base\CreativeWork;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *  shortName="MenuCategory",
 *  attributes={
 *    "normalization_context"={"groups"={"restaurant"}}
 *  },
 *  collectionOperations={},
 *  itemOperations={
 *    "get"={"method"="GET"}
 *  })
 */
class MenuCategory extends CreativeWork
{
    /**
     * @var int
     */
    private $id;

    /**
     * Gets id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
}
