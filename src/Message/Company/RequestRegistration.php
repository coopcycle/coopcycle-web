<?php
declare(strict_types=1);

namespace AppBundle\Message\Company;

use AppBundle\Entity\Address;

class RequestRegistration
{
    private string $companyName;
    private Address $address;
    private int $collaboratorNumber;
    private int $mealEstimate;
    private array $businessReferent;

    public function __construct(
        string $companyName,
        Address $address,
        array $businessReferent,
        int $collaboratorNumber,
        int $mealEstimate
    ) {
        $this->companyName = $companyName;
        $this->address = $address;
        $this->collaboratorNumber = $collaboratorNumber;
        $this->mealEstimate = $mealEstimate;
        $this->businessReferent = $businessReferent;
    }

    /**
     * @return string
     */
    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    /**
     * @return int
     */
    public function getCollaboratorNumber(): int
    {
        return $this->collaboratorNumber;
    }

    /**
     * @return int
     */
    public function getMealEstimate(): int
    {
        return $this->mealEstimate;
    }

    /**
     * @return array
     */
    public function getBusinessReferent(): array
    {
        return $this->businessReferent;
    }
}
