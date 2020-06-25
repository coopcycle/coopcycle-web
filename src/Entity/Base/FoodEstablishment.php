<?php

namespace AppBundle\Entity\Base;

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
 */
abstract class FoodEstablishment extends LocalBusiness
{
    /**
     * Sets servesCuisine.
     *
     * @param string $servesCuisine
     *
     * @return $this
     */
    abstract public function setServesCuisine($servesCuisine);

    /**
     * Gets servesCuisine.
     *
     * @return string
     */
    abstract public function getServesCuisine();
}
