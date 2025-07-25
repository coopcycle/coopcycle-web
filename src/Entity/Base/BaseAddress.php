<?php

namespace AppBundle\Entity\Base;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class BaseAddress extends Place
{
    /**
     * @var string Additional instructions about the place
     */
    #[Groups(['address', 'address_create', 'task', 'task_create', 'task_edit', 'order_update', 'restaurant_delivery'])]
    #[Assert\Type(type: 'string')]
    #[ApiProperty(types: ['https://schema.org/addressLocality'])]
    protected $description;

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription(?string $description = null)
    {
        $this->description = $description;
    }
}
