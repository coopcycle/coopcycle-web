<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Timestampable\Traits\Timestampable;
use Gedmo\SoftDeleteable\SoftDeleteable as SoftDeleteableInterface;
use Gedmo\SoftDeleteable\Traits\SoftDeleteable;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(operations: [new Get(security: 'is_granted(\'ROLE_ADMIN\')'), new Patch(security: 'is_granted(\'ROLE_ADMIN\')'), new Delete(security: 'is_granted(\'ROLE_ADMIN\')'), new GetCollection(security: 'is_granted(\'ROLE_DISPATCHER\')'), new Post(security: 'is_granted(\'ROLE_ADMIN\')')], normalizationContext: ['groups' => ['vehicle', 'warehouse']], denormalizationContext: ['groups' => ['vehicle_create']], order: ['name' => 'ASC'])]
class Vehicle implements SoftDeleteableInterface
{
    use Timestampable;
    use SoftDeleteable;

    #[Groups(['vehicle', 'vehicle_create'])]
    protected $id;

    #[Groups(['vehicle', 'vehicle_create'])]
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    protected $name;

    #[Groups(['vehicle', 'vehicle_create'])]
    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    protected $maxVolumeUnits;

    #[Groups(['vehicle', 'vehicle_create'])]
    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    protected $maxWeight;

    /**
     * @Assert\CssColor
     */
    #[Groups(['vehicle', 'vehicle_create'])]
    #[Assert\NotBlank]
    protected $color;

    #[Groups(['vehicle', 'vehicle_create'])]
    #[Assert\Type('boolean')]
    protected $isElectric;

    #[Groups(['vehicle', 'vehicle_create'])]
    #[Assert\Type('integer')]
    protected $electricRange;

    #[Groups(['vehicle', 'vehicle_create'])]
    #[Assert\Type('integer')]
    protected $co2emissions;

    #[Groups(['vehicle', 'vehicle_create'])]
    #[Assert\NotBlank]
    #[Assert\Type(Warehouse::class)] // ]
    protected $warehouse;

    #[Groups(['vehicle'])]
    protected $compatibleTrailers;

    public function __construct() {
        $this->compatibleTrailers = new ArrayCollection();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getMaxVolumeUnits()
    {
        return $this->maxVolumeUnits;
    }

    /**
     * @param mixed $volumeUnits
     *
     * @return self
     */
    public function setMaxVolumeUnits($volumeUnits)
    {
        $this->maxVolumeUnits = $volumeUnits;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getMaxWeight()
    {
        return $this->maxWeight;
    }

    /**
     * @param mixed $maxWeight
     *
     * @return self
     */
    public function setMaxWeight($maxWeight)
    {
        $this->maxWeight = $maxWeight;

        return $this;
    }

    /**
     * Get the value of color
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Set the value of color
     *
     * @return  self
     */
    public function setColor($color)
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Get the value of isElectric
     */
    public function getIsElectric()
    {
        return $this->isElectric;
    }

    /**
     * Set the value of isElectric
     *
     * @return  self
     */
    public function setIsElectric($isElectric)
    {
        $this->isElectric = $isElectric;

        return $this;
    }

    /**
     * Get the value of electricRange
     */
    public function getElectricRange()
    {
        return $this->electricRange;
    }

    /**
     * Set the value of electricRange
     *
     * @return  self
     */
    public function setElectricRange($electricRange)
    {
        $this->electricRange = $electricRange;

        return $this;
    }

    /**
     * Get the value of warehouse
     */
    public function getWarehouse()
    {
        return $this->warehouse;
    }

    /**
     * Set the value of warehouse
     *
     * @return  self
     */
    public function setWarehouse($warehouse)
    {
        $this->warehouse = $warehouse;

        return $this;
    }

    /**
     * Get the value of compatibleTrailers
     */
    public function getCompatibleTrailers()
    {
        return $this->compatibleTrailers->map(function ($vehicleTrailer) {
            return $vehicleTrailer->getTrailer();
        });
    }

    public function clearTrailers()
    {
        foreach($this->compatibleTrailers as $item) {
            $item->setVehicle(null);
        }
        return $this->compatibleTrailers->clear();
    }


    /**
     * Get the value of co2emissions
     */ 
    public function getCo2emissions()
    {
        return $this->co2emissions;
    }

    /**
     * Set the value of co2emissions
     *
     * @return  self
     */ 
    public function setCo2emissions($co2emissions)
    {
        $this->co2emissions = $co2emissions;

        return $this;
    }
}
