<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Action\TimeSlot\Choices as ChoicesController;
use AppBundle\Action\TimeSlot\StoreOpeningHours as OpeningHours;
use AppBundle\Entity\LocalBusiness\FulfillmentMethod;
use AppBundle\Entity\LocalBusiness\ShippingOptionsInterface;
use AppBundle\Utils\OpeningHoursSpecification;
use AppBundle\Validator\Constraints\NotOverlappingOpeningHours as AssertNotOverlappingOpeningHours;
use AppBundle\Validator\Constraints\TimeSlotDelete as AssertCanDelete;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Get(),
        new Delete(
            security: 'is_granted(\'ROLE_ADMIN\')',
            validationContext: ['groups' => ['deleteValidation']]
        ),
        new Get(
            uriTemplate: '/time_slots/{id}/choices',
            controller: OpeningHours::class,
            normalizationContext: ['groups' => ['time_slot_choices'], 'api_sub_level' => true],
            security: 'is_granted(\'edit\', object)'
        ),
        new GetCollection(security: 'is_granted(\'ROLE_ADMIN\')'),
        new GetCollection(
            uriTemplate: '/time_slots/choices',
            controller: ChoicesController::class,
            status: 200,
            read: false,
            write: false,
            normalizationContext: ['groups' => ['time_slot_choices'], 'api_sub_level' => true],
            security: 'is_granted(\'ROLE_OAUTH2_DELIVERIES\')',
            openapiContext: ['summary' => 'Retrieves choices for time slot']
        )
    ],
    normalizationContext: ['groups' => ['time_slot']],
    paginationClientEnabled: true
)]
#[AssertCanDelete(groups: ['deleteValidation'])]
class TimeSlot
{
    use Timestampable;

    private $id;

    /**
     * @var string
     */
    #[Groups(['time_slot', 'store_time_slots'])]
    private $name;

    /**
     * @var string
     */
    #[Groups(['time_slot'])]
    private $interval = '2 days';

    /**
     * @var bool
     */
    #[Groups(['time_slot'])]
    private $workingDaysOnly = true;

    /**
     * kept for backward compatibility, to be deleted when https://github.com/coopcycle/coopcycle-app/issues/1771 is solved
     * @deprecated
     */
    #[Groups(['time_slot'])]
    public $choices = [];

    /**
     * @var string
     */
    #[Groups(['time_slot'])]
    private $priorNotice;

    /**
     * @var string
     */
    private $sameDayCutoff;

    /**
     * @var array
     */
    #[AssertNotOverlappingOpeningHours(groups: ['Default'])]
    private $openingHours = [];

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * kept for backward compatibility, to be deleted when https://github.com/coopcycle/coopcycle-app/issues/1771 is solved
     * @deprecated
     */
    public function getChoices()
    {
        return [];
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getInterval()
    {
        return $this->interval;
    }

    /**
     * @param mixed $interval
     *
     * @return self
     */
    public function setInterval($interval)
    {
        $this->interval = $interval;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getOpeningHours()
    {
        return $this->openingHours;
    }

    /**
     * @param array $openingHours
     *
     * @return self
     */
    public function setOpeningHours($openingHours)
    {
        $this->openingHours = $openingHours;

        return $this;
    }

    public function isWorkingDaysOnly(): bool
    {
        return $this->workingDaysOnly;
    }

    /**
     * @return self
     */
    public function setWorkingDaysOnly(bool $workingDaysOnly)
    {
        $this->workingDaysOnly = $workingDaysOnly;

        return $this;
    }

    #[Groups(['time_slot'])]
    public function getOpeningHoursSpecification()
    {
        return array_map(function (OpeningHoursSpecification $spec) {
            return $spec->jsonSerialize();
        }, OpeningHoursSpecification::fromOpeningHours($this->getOpeningHours()));
    }

    /**
     * @return string
     */
    public function getPriorNotice()
    {
        return $this->priorNotice;
    }

    /**
     * @param string $priorNotice
     *
     * @return self
     */
    public function setPriorNotice($priorNotice)
    {
        $this->priorNotice = $priorNotice;

        return $this;
    }

    /**
     * @return string
     */
    public function getSameDayCutoff()
    {
        return $this->sameDayCutoff;
    }

    /**
     * @param string $sameDayCutoff
     *
     * @return self
     */
    public function setSameDayCutoff($sameDayCutoff)
    {
        $this->sameDayCutoff = $sameDayCutoff;

        return $this;
    }

    public static function create(FulfillmentMethod $fulfillmentMethod, ShippingOptionsInterface $options): TimeSlot
    {
        $timeSlot = new self();
        $timeSlot->setWorkingDaysOnly(false);

        $minutes = $fulfillmentMethod->getOrderingDelayMinutes();
        if ($minutes > 0) {
            $hours = (int) $minutes / 60;
            $timeSlot->setPriorNotice(sprintf('%d %s', $hours, ($hours > 1 ? 'hours' : 'hour')));
        }

        $shippingOptionsDays = $options->getShippingOptionsDays();
        if ($shippingOptionsDays > 0) {
            $timeSlot->setInterval(sprintf('%d days', $shippingOptionsDays));
        }

        $timeSlot->setOpeningHours(
            $fulfillmentMethod->getOpeningHours()
        );

        return $timeSlot;
    }
}
