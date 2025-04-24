<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Api\State\ValidationAwareRemoveProcessor;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use AppBundle\Validator\Constraints\WarehouseDelete as AssertCanDelete;
use Gedmo\SoftDeleteable\Traits\SoftDeleteable;
use Gedmo\Timestampable\Traits\Timestampable;

#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_DISPATCHER')"),
        new Delete(
            security: 'is_granted(\'ROLE_ADMIN\')',
            validationContext: ['groups' => ['deleteValidation']],
            processor: ValidationAwareRemoveProcessor::class
        ),
        new GetCollection(security: 'is_granted(\'ROLE_DISPATCHER\')'),
        new Post(security: 'is_granted(\'ROLE_ADMIN\')')
    ],
    normalizationContext: ['groups' => ['warehouse', 'address']],
    denormalizationContext: ['groups' => ['warehouse_create', 'address_create']]
)]
#[AssertCanDelete(groups: ['deleteValidation'])]
class Warehouse
{
    use Timestampable;
    use SoftDeleteable;

    #[Groups(['warehouse'])]
    protected $id;

    #[Assert\NotBlank]
    #[Groups(['warehouse', 'warehouse_create'])]
    protected $name;

    #[Assert\NotNull]
    #[Assert\Valid]
    #[Groups(['address', 'address_create'])]
    protected $address;

    protected $vehicles;

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

    /**
     * Get the value of vehicles
     */
    public function getVehicles()
    {
        return $this->vehicles;
    }
}
