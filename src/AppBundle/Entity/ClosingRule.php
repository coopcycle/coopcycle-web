<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(iri="https://schema.org/OpeningHoursSpecification",
 *   shortName="OpeningHoursSpecification",
 *   collectionOperations={
 *   },
 *   itemOperations={
 *     "get"={"method"="GET"},
 *     "delete"={
 *       "method"="DELETE",
 *       "is_granted('ROLE_RESTAURANT') and user.ownsRestaurant(object.getRestaurant())"
 *     },
 *   },
 * )
 */
class ClosingRule
{

    /**
     * @var int
     * @Groups({"restaurant", "planning"})
     */
    private $id;

    private $restaurant;

    /**
     * @Groups({"restaurant", "planning"})
     */
    private $startDate;

    /**
     * @Groups({"restaurant", "planning"})
     */
    private $endDate;

    /**
     * @Groups({"planning"})
     */
    private $reason;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getRestaurant()
    {
        return $this->restaurant;
    }

    /**
     * @param mixed $restaurant
     */
    public function setRestaurant($restaurant)
    {
        $this->restaurant = $restaurant;
    }

    /**
     * @return mixed
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * @param mixed $startDate
     */
    public function setStartDate($startDate)
    {
        $this->startDate = $startDate;
    }

    /**
     * @return mixed
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * @param mixed $endDate
     */
    public function setEndDate($endDate)
    {
        $this->endDate = $endDate;
    }

    /**
     * @return mixed
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * @param mixed $reason
     */
    public function setReason($reason)
    {
        $this->reason = $reason;
    }
}
