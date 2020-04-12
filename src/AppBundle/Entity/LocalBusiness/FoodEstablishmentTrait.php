<?php

namespace AppBundle\Entity\LocalBusiness;

use ApiPlatform\Core\Annotation\ApiProperty;

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

    public function addServesCuisine($servesCuisine)
    {
        $this->servesCuisine->add($servesCuisine);

        return $this;
    }

    public function getServesCuisine()
    {
        return $this->servesCuisine;
    }
}
