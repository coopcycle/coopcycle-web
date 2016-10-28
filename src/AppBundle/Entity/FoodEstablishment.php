<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A food-related business.
 *
 * @see http://schema.org/FoodEstablishment Documentation on Schema.org
 *
 * @ORM\MappedSuperclass
 */
abstract class FoodEstablishment extends LocalBusiness
{
    /**
     * @var string The cuisine of the restaurant.
     *
     * @ORM\Column(nullable=true)
     * @Assert\Type(type="string")
     * @ApiProperty(iri="https://schema.org/servesCuisine")
     */
    private $servesCuisine;

    /**
     * Sets servesCuisine.
     *
     * @param string $servesCuisine
     *
     * @return $this
     */
    public function setServesCuisine($servesCuisine)
    {
        $this->servesCuisine = $servesCuisine;

        return $this;
    }

    /**
     * Gets servesCuisine.
     *
     * @return string
     */
    public function getServesCuisine()
    {
        return $this->servesCuisine;
    }
}
