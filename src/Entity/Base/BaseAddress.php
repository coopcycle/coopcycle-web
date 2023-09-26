<?php

namespace AppBundle\Entity\Base;

use ApiPlatform\Core\Annotation\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class BaseAddress extends Place
{
    /**
     * @var string Additional instructions about the delivery
     *
     * @Groups({"address_create", "task", "task_create", "task_edit", "order_update", "restaurant_delivery"})
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/description")
     */
    protected $description;

    /**
     * @var string Additional instructions about the place
     *
     * @Groups({"address_create", "task", "task_create", "task_edit", "order_update", "restaurant_delivery"})
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/addressLocality")
     */
    protected $complement;

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
    public function getComplement()
    {
        return $this->complement;
    }

    /**
     * @param string $complement
     */
    public function setComplement(string $complement = null)
    {
        $this->complement = $complement;
    }
}
