<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @see http://schema.org/Place Documentation on Schema.org
 *
 * @ORM\Entity
 * @ORM\Table(
 *     options={"spatial_indexes"={"idx_address_geo"}},
 *     indexes={
 *         @ORM\Index(name="idx_address_geo", columns={"geo"}, flags={"spatial"})
 *     }
 * )
 * @ApiResource
 */
class Address extends Place
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
     * Gets id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
}
