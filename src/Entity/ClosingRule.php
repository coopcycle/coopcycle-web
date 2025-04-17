<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Action\Restaurant\DeleteClosingRule;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(operations: [new Get(), new Delete(controller: DeleteClosingRule::class, security: 'is_granted(\'delete\', object)')], shortName: 'OpeningHoursSpecification', types: ['https://schema.org/OpeningHoursSpecification'])]
class ClosingRule
{
    /**
     * @var int
     */
    #[Groups(['restaurant', 'planning'])]
    private $id;

    #[Groups(['restaurant', 'planning'])]
    private $startDate;

    #[Groups(['restaurant', 'planning'])]
    private $endDate;

    #[Groups(['planning'])]
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

    public static function create($startDate, $endDate)
    {
        $r = new self();
        $r->setStartDate(is_string($startDate) ? new \DateTime($startDate) : $startDate);
        $r->setEndDate(is_string($endDate) ? new \DateTime($endDate) : $endDate);

        return $r;
    }
}
