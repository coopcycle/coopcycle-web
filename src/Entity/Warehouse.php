<?php

namespace AppBundle\Entity;

use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Core\Annotation\ApiResource;
use Gedmo\Timestampable\Traits\Timestampable;

/**
 * @ApiResource(
 *   attributes={
 *     "normalization_context"={"groups"={"warehouse", "address"}},
 *     "denormalization_context"={"groups"={"warehouse_create", "address_create"}}
 *   },
 *   collectionOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_DISPATCHER') or is_granted('ROLE_ADMIN')",
 *      },
 *     "post"={
 *       "method"="POST",
 *       "access_control"="is_granted('ROLE_ADMIN')",
 *      },
 *   }
 * )
 */
class Warehouse
{
    use Timestampable;

    protected $id;

    /**
    * @Groups({"warehouse", "warehouse_create"})
    */
    protected $name;

    /**
    * @Groups({"address", "address_create"})
    */
    protected $address;

    /**
     * Get the value of id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id
     *
     * @return  self
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the value of name
     *
     * @return  self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the value of address
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Set the value of address
     *
     * @return  self
     */
    public function setAddress($address)
    {
        $this->address = $address;

        return $this;
    }
}