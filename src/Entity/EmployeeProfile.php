<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use AppBundle\Api\State\EmployeeProfileProcessor;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            paginationEnabled: false,
            security: 'is_granted(\'ROLE_ADMIN\')'
        ),
        new Post(
            security: 'is_granted(\'ROLE_ADMIN\')',
            processor: EmployeeProfileProcessor::class
        ),
        new Get(security: 'is_granted(\'ROLE_ADMIN\')'),
        new Put(
            security: 'is_granted(\'ROLE_ADMIN\')',
            processor: EmployeeProfileProcessor::class
        ),
        new Delete(security: 'is_granted(\'ROLE_ADMIN\')'),
    ],
    normalizationContext: ['groups' => ['employee_profile']],
    denormalizationContext: ['groups' => ['employee_profile_write']]
)]
class EmployeeProfile
{
    const SALARY_TYPE_HOURLY = 'hourly';
    const SALARY_TYPE_MONTHLY = 'monthly';

    #[Groups(['employee_profile'])]
    protected $id;

    #[Groups(['employee_profile', 'employee_profile_write'])]
    #[Assert\NotBlank]
    protected ?User $user = null;

    #[Groups(['employee_profile', 'employee_profile_write'])]
    protected ?\DateTime $contractStartDate = null;

    #[Groups(['employee_profile', 'employee_profile_write'])]
    protected ?\DateTime $dateOfBirth = null;

    #[Groups(['employee_profile', 'employee_profile_write'])]
    protected ?string $addressStreet = null;

    #[Groups(['employee_profile', 'employee_profile_write'])]
    protected ?string $addressPostalCode = null;

    #[Groups(['employee_profile', 'employee_profile_write'])]
    protected ?string $addressLocality = null;

    #[Groups(['employee_profile', 'employee_profile_write'])]
    protected ?string $addressCountry = null;

    #[Groups(['employee_profile', 'employee_profile_write'])]
    #[Assert\Choice(choices: [self::SALARY_TYPE_HOURLY, self::SALARY_TYPE_MONTHLY])]
    protected ?string $salaryType = null;

    #[Groups(['employee_profile', 'employee_profile_write'])]
    protected ?string $salaryAmount = null;

    #[Groups(['employee_profile', 'employee_profile_write'])]
    protected ?string $weeklyContractedHours = null;

    #[Groups(['employee_profile'])]
    protected $createdAt;

    protected $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getContractStartDate(): ?\DateTime
    {
        return $this->contractStartDate;
    }

    public function setContractStartDate(?\DateTime $contractStartDate): self
    {
        $this->contractStartDate = $contractStartDate;

        return $this;
    }

    public function getDateOfBirth(): ?\DateTime
    {
        return $this->dateOfBirth;
    }

    public function setDateOfBirth(?\DateTime $dateOfBirth): self
    {
        $this->dateOfBirth = $dateOfBirth;

        return $this;
    }

    public function getAddressStreet(): ?string
    {
        return $this->addressStreet;
    }

    public function setAddressStreet(?string $addressStreet): self
    {
        $this->addressStreet = $addressStreet;

        return $this;
    }

    public function getAddressPostalCode(): ?string
    {
        return $this->addressPostalCode;
    }

    public function setAddressPostalCode(?string $addressPostalCode): self
    {
        $this->addressPostalCode = $addressPostalCode;

        return $this;
    }

    public function getAddressLocality(): ?string
    {
        return $this->addressLocality;
    }

    public function setAddressLocality(?string $addressLocality): self
    {
        $this->addressLocality = $addressLocality;

        return $this;
    }

    public function getAddressCountry(): ?string
    {
        return $this->addressCountry;
    }

    public function setAddressCountry(?string $addressCountry): self
    {
        $this->addressCountry = $addressCountry;

        return $this;
    }

    public function getSalaryType(): ?string
    {
        return $this->salaryType;
    }

    public function setSalaryType(?string $salaryType): self
    {
        $this->salaryType = $salaryType;

        return $this;
    }

    public function getSalaryAmount(): ?string
    {
        return $this->salaryAmount;
    }

    public function setSalaryAmount(?string $salaryAmount): self
    {
        $this->salaryAmount = $salaryAmount;

        return $this;
    }

    public function getWeeklyContractedHours(): ?string
    {
        return $this->weeklyContractedHours;
    }

    public function setWeeklyContractedHours(?string $weeklyContractedHours): self
    {
        $this->weeklyContractedHours = $weeklyContractedHours;

        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}
