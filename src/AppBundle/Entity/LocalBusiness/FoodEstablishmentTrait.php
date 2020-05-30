<?php

namespace AppBundle\Entity\LocalBusiness;

use ApiPlatform\Core\Annotation\ApiProperty;
use AppBundle\Entity\Cuisine;

trait FoodEstablishmentTrait
{
    /**
     * @var mixed The cuisine of the restaurant.
     *
     * @ApiProperty(iri="https://schema.org/servesCuisine")
     */
    protected $servesCuisine;

    public function setServesCuisine($servesCuisine)
    {
        $this->servesCuisine = $servesCuisine;

        return $this;
    }

    public function addServesCuisine(Cuisine $cuisine)
    {
        $this->servesCuisine->add($cuisine);

        return $this;
    }

    public function getServesCuisine()
    {
        return $this->servesCuisine;
    }
}
