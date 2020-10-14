<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Restaurant\DeleteClosingRule;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(iri="https://schema.org/OpeningHoursSpecification",
 *   shortName="OpeningHoursSpecification",
 *   collectionOperations={},
 *   itemOperations={
 *     "get"={"method"="GET"},
 *     "delete"={
 *       "method"="DELETE",
 *       "controller"=DeleteClosingRule::class,
 *       "security"="is_granted('ROLE_RESTAURANT') and is_granted('delete', object)"
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
