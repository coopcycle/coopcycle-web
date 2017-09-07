<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Base\BaseAddress;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiResource;


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
 * @ApiResource(iri="http://schema.org/Place",
 *   collectionOperations={
 *     "get"={"method"="GET"},
 *     "create_address"={"route_name"="create_address"}
 *   },
 *   itemOperations={
 *     "get"={"method"="GET"},
 *   },
 * )
 */
class Address extends BaseAddress
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
