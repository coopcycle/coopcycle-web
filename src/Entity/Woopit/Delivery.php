<?php

namespace AppBundle\Entity\Woopit;

use ApiPlatform\Core\Action\NotFoundAction;
use ApiPlatform\Core\Annotation\ApiResource;
use Gedmo\Timestampable\Traits\Timestampable;

/**
 * @ApiResource(
 *   collectionOperations={
 *     "get": {
 *       "method"="GET",
 *       "controller"=NotFoundAction::class,
 *       "path"="/woopit/deliveries",
 *       "read"=false,
 *       "output"=false
 *     }
 *   },
 *   itemOperations={
 *     "get": {
 *       "method"="GET",
 *       "controller"=NotFoundAction::class,
 *       "read"=false,
 *       "output"=false
 *     }
 *   }
 * )
 */
class Delivery
{
    use Timestampable;

    private $id;
    private $delivery;

    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getDelivery()
    {
        return $this->delivery;
    }

    /**
     * @param mixed $delivery
     *
     * @return self
     */
    public function setDelivery($delivery)
    {
        $this->delivery = $delivery;

        return $this;
    }
}
