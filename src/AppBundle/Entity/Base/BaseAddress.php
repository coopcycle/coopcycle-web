<?php

namespace AppBundle\Entity\Base;

use ApiPlatform\Core\Annotation\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;

class BaseAddress extends Place
{
    /**
     * @var string Additional instructions about the place
     *
     * @ORM\Column(nullable=true)
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/addressLocality")
     */
    protected $description;

    /**
     * @var string Floor
     *
     * @ORM\Column(nullable=true)
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/addressLocality")
     */
    protected $floor;

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description || '';
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getFloor(): string
    {
        return $this->floor || '';
    }

    /**
     * @param string $floor
     */
    public function setFloor(string $floor)
    {
        $this->floor = $floor;
    }
}
