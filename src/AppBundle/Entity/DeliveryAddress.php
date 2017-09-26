<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Base\BaseAddress;
use Doctrine\Orm\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;

/**
 * @see http://schema.org/Place Documentation on Schema.org
 *
 * @ORM\Entity
 * @ORM\Table(
 *     options={"spatial_indexes"={"idx_delivery_address_geo"}},
 *     indexes={
 *         @ORM\Index(name="idx_delivery_address_geo", columns={"geo"}, flags={"spatial"})
 *     }
 * )
 * @ApiResource(iri="http://schema.org/Place")
 *
 */

class DeliveryAddress extends BaseAddress {

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;


    /**
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\Delivery", mappedBy="deliveryAddress", cascade={"all"})
     */
    protected $delivery;

    /**
     * @return Delivery
     */
    public function getDelivery()
    {
        return $this->delivery;
    }

    /**
     * @param Delivery
     */
    public function setDelivery($delivery)
    {
        $this->delivery = $delivery;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

}
