<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;
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
     * @ORM\ManyToMany(targetEntity="Cuisine", cascade={"persist"})
     * @ORM\JoinTable(inverseJoinColumns={@ORM\JoinColumn()})
     * @ORM\OrderBy({"name" = "ASC"})
     * @ApiProperty(iri="https://schema.org/servesCuisine")
     * @Groups({"restaurant"})
     */
    protected $servesCuisine;

    public function __construct()
    {
        $this->servesCuisine = new ArrayCollection();
    }

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
     * Adds servesCuisine.
     *
     * @param string $servesCuisine
     *
     * @return $this
     */
    public function addServesCuisine(Cuisine $servesCuisine)
    {
        $this->servesCuisine->add($servesCuisine);

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
