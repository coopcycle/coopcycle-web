<?php

namespace AppBundle\Entity\Base;

use ApiPlatform\Core\Annotation\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class BaseAddress extends Place
{
    /**
     * @var string Additional instructions about the place
     *
     * @Groups({"address", "address_create", "task", "task_edit", "order_update"})
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/addressLocality")
     */
    protected $description;

    /**
     * @var string Floor
     *
     * @Groups({"address", "task", "task", "task_edit"})
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/addressLocality")
     */
    protected $floor;

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description = null)
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getFloor()
    {
        return $this->floor;
    }

    /**
     * @param string $floor
     */
    public function setFloor(string $floor = null)
    {
        $this->floor = $floor;
    }
}
