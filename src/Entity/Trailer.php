<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use Symfony\Component\Serializer\Annotation\Groups;
use AppBundle\Action\Trailer\SetVehicles;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Timestampable\Traits\Timestampable;
use Gedmo\SoftDeleteable\SoftDeleteable as SoftDeleteableInterface;
use Gedmo\SoftDeleteable\Traits\SoftDeleteable;
use Symfony\Component\Validator\Constraints as Assert;


#[ApiResource(operations: [new Get(security: 'is_granted(\'ROLE_ADMIN\')'), new Delete(security: 'is_granted(\'ROLE_ADMIN\')'), new Patch(security: 'is_granted(\'ROLE_ADMIN\')'), new Put(security: 'is_granted(\'ROLE_ADMIN\')', uriTemplate: '/trailers/{id}/vehicles', controller: SetVehicles::class, write: false, read: false), new GetCollection(security: 'is_granted(\'ROLE_DISPATCHER\')'), new Post(security: 'is_granted(\'ROLE_ADMIN\')')], normalizationContext: ['groups' => ['trailer']], denormalizationContext: ['groups' => ['trailer_create']], order: ['name' => 'ASC'])]
class Trailer implements SoftDeleteableInterface
{
    use Timestampable;
    use SoftDeleteable;

    #[Groups(['trailer'])]
    protected $id;

    #[Groups(['trailer', 'trailer_create'])]
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    protected $name;

    #[Groups(['trailer', 'trailer_create'])]
    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    protected $maxVolumeUnits;

    #[Groups(['trailer', 'trailer_create'])]
    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    protected $maxWeight;

    /**
     * @Assert\CssColor
     */
    #[Groups(['trailer', 'trailer_create'])]
    #[Assert\NotBlank]
    protected $color;

    #[Groups(['trailer', 'trailer_create'])]
    #[Assert\Type('boolean')]
    protected $isElectric;

    #[Groups(['trailer', 'trailer_create'])]
    #[Assert\Type('integer')]
    protected $electricRange;

    #[Groups(['trailer'])]
    protected $compatibleVehicles;

    public function __construct() {
        $this->compatibleVehicles = new ArrayCollection();
    }

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
     * Get the value of maxVolumeUnits
     */
    public function getMaxVolumeUnits()
    {
        return $this->maxVolumeUnits;
    }

    /**
     * Set the value of maxVolumeUnits
     *
     * @return  self
     */
    public function setMaxVolumeUnits($maxVolumeUnits)
    {
        $this->maxVolumeUnits = $maxVolumeUnits;

        return $this;
    }

    /**
     * Get the value of maxWeight
     */
    public function getMaxWeight()
    {
        return $this->maxWeight;
    }

    /**
     * Set the value of maxWeight
     *
     * @return  self
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

    public function getCompatibleVehicles() {
        return $this->compatibleVehicles->map(function ($vehicleCompat) {
            return $vehicleCompat->getVehicle();
        });
    }

    public function hasVehicleCompat($vehicle): bool
    {
        return $this->getCompatibleVehicles()->contains($vehicle);
    }

    public function clearVehicles()
    {
        foreach($this->compatibleVehicles as $item) {
            $item->setTrailer(null);
        }
        return $this->compatibleVehicles->clear();
    }

    public function setCompatibleVehicles($vehicles)
    {
        $this->clearVehicles();

        foreach($vehicles as $vehicle) {
            if (!$this->hasVehicleCompat($vehicle)) {
                $vehicleTrailer = new Vehicle\Trailer();
                $vehicleTrailer->setVehicle($vehicle);
                $vehicleTrailer->setTrailer($this);
                $this->compatibleVehicles->add($vehicleTrailer);
            }
        }
    }
}
