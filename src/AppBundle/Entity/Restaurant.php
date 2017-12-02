<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Base\FoodEstablishment;
use AppBundle\Utils\TimeRange;
use AppBundle\Utils\ValidationUtils;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validation;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * A restaurant.
 *
 * @see http://schema.org/Restaurant Documentation on Schema.org
 *
 * @ORM\Entity(repositoryClass="AppBundle\Entity\RestaurantRepository")
 * @ApiResource(iri="http://schema.org/Restaurant",
 *   attributes={
 *     "filters"={"restaurant.search"},
 *     "normalization_context"={"groups"={"restaurant", "place", "order"}}
 *   },
 *   collectionOperations={
 *     "get"={"method"="GET"}
 *   },
 *   itemOperations={
 *     "get"={"method"="GET"}
 *   }
 * )

 * @Vich\Uploadable
 */
class Restaurant extends FoodEstablishment
{
    /**
     *  Delay for preparation (in minutes)
     */
    const PREPARATION_DELAY = 30;

    /**
     *  Delay for delivery (in minutes)
     */
    const DELIVERY_DELAY = 15;

    /**
     *  Delay for preparation + delivery (in minutes)
     */
    const PREPARATION_AND_DELIVERY_DELAY = self::PREPARATION_DELAY + self::DELIVERY_DELAY;

    /**
     * We need to take into account the time the user will take to order
     * Otherwise we may trip too often in the following scenario :
     *  - customer starts to shop with the first available date
     *  - cart becomes invalid/out-of-date in the checkout process
     */
    const ORDERING_DELAY = 10;

    /**
     *  We allow ordering at J+1
     */
    const NUMBER_OF_AVAILABLE_DAYS = 2;

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string The name of the item
     *
     * @Assert\Type(type="string")
     * @ORM\Column(nullable=true)
     * @ApiProperty(iri="http://schema.org/name")
     * @Groups({"restaurant", "order"})
     * @Assert\NotBlank(message="restaurant.name.notBlank", groups={"activable"})
     */
    protected $name;

    /**
     * @var string The cuisine of the restaurant.
     *
     * @ORM\ManyToMany(targetEntity="Cuisine", cascade={"persist"})
     * @ORM\JoinTable(inverseJoinColumns={@ORM\JoinColumn()})
     * @ORM\OrderBy({"name"="ASC"})
     * @ApiProperty(iri="https://schema.org/servesCuisine")
     * @Groups({"restaurant"})
     */
    protected $servesCuisine;

    /**
     * @var boolean Is the restaurant enabled?
     *
     * A disable restaurant is not shown to visitors, either on search page or on its detail page.
     *
     * @ORM\Column(type="boolean", options={"default": false})
     * @Groups({"restaurant"})
     */
    protected $enabled = false;

    /**
     * @Vich\UploadableField(mapping="restaurant_image", fileNameProperty="imageName")
     * @Assert\File(maxSize = "1024k")
     * @var File
     */
    private $imageFile;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @var string
     */
    private $imageName;

    /**
     * @ORM\OneToOne(targetEntity="Address", cascade={"all"})
     * @Groups({"restaurant"})
     * @Assert\NotBlank(groups={"activable"})
     */
    private $address;

    /**
     * @var string The website of the restaurant.
     *
     * @ORM\Column(nullable=true)
     * @ApiProperty(iri="https://schema.org/URL")
     */
    private $website;

    /**
     * @var string The telephone number..
     *
     * @ORM\Column(nullable=true)
     * @Assert\Type(type="string")
     * @Assert\NotBlank(message="restaurant.telephone.notBlank", groups={"activable"})
     */
    protected $telephone;

    /**
     * @var string The delivery service of the restaurant.
     *
     * @ORM\OneToOne(targetEntity="DeliveryService", cascade={"persist"})
     * @ORM\JoinColumn(name="delivery_service_id", referencedColumnName="id")
     * @Assert\NotBlank(message="restaurant.deliveryService.notBlank", groups={"activable"})
     */
    private $deliveryService;

    /**
     * @var string The Stripe params of the restaurant.
     *
     * @ORM\ManyToOne(targetEntity="StripeParams")
     */
    private $stripeParams;

    /**
     * @var string The menu of the restaurant.
     *
     * @ORM\OneToOne(targetEntity="Menu", inversedBy="restaurant", cascade={"all"})
     * @ORM\JoinColumn(name="menu_id")
     * @ApiProperty(iri="https://schema.org/Menu")
     * @Groups({"restaurant"})
     */
    private $hasMenu;

    private $maxDistance = 3000;

    /**
     * @var string The opening hours for a business. Opening hours can be specified as a weekly time range, starting with days, then times per day. Multiple days can be listed with commas ',' separating each day. Day or time ranges are specified using a hyphen '-'.
     *             - Days are specified using the following two-letter combinations: `Mo`, `Tu`, `We`, `Th`, `Fr`, `Sa`, `Su`.
     *             - Times are specified using 24:00 time. For example, 3pm is specified as `15:00`.
     *             - Here is an example: `<time itemprop="openingHours" datetime="Tu,Th 16:00-20:00">Tuesdays and Thursdays 4-8pm</time>`.
     *             - If a business is open 7 days a week, then it can be specified as `<time itemprop="openingHours" datetime="Mo-Su">Monday through Sunday, all day</time>`.
     *
     * @ORM\Column(type="json_array", nullable=true)
     * @ApiProperty(iri="https://schema.org/openingHours")
     * @Groups({"restaurant"})
     * @Assert\NotBlank(message="restaurant.openingHours.notBlank", groups={"activable"})
     */
    protected $openingHours;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     */
    private $updatedAt;

    /**
     * @var Contract
     * @ORM\OneToOne(targetEntity="Contract", mappedBy="restaurant", cascade={"persist"})
     * @Assert\NotBlank(message="restaurant.contract.notBlank", groups={"activable"})
     */
    private $contract;

    public function __construct()
    {
        $this->servesCuisine = new ArrayCollection();
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }

    /**
     * Gets id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gets name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled)
    {
        $this->enabled = $enabled;
    }

    public function getWebsite()
    {
        return $this->website;
    }

    public function setWebsite($website)
    {
        $this->website = $website;

        return $this;
    }

    public function setImageName($imageName)
    {
        $this->imageName = $imageName;

        return $this;
    }

    public function getImageName()
    {
        return $this->imageName;
    }

    public function getAddress()
    {
        return $this->address;
    }

    public function setAddress(Address $address)
    {
        $this->address = $address;

        return $this;
    }

    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the  update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     *
     * @param File|Symfony\Component\HttpFoundation\File\UploadedFile $image
     */
    public function setImageFile(File $image = null)
    {
        $this->imageFile = $image;

        if ($image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    /**
     * @return File|null
     */
    public function getImageFile()
    {
        return $this->imageFile;
    }

    /**
     * @return boolean
     */
    public function isOpen(\DateTime $now = null)
    {
        if (!$now) {
            $now = new \DateTime();
        }

        foreach ($this->openingHours as $openingHour) {
            $timeRange = new TimeRange($openingHour);
            if ($timeRange->isOpen($now)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the next date the restaurant will be opened at.
     *
     * @param \DateTime|null $now
     * @return mixed
     */
    public function getNextOpeningDate(\DateTime $now = null)
    {
        if (!$now) {
            $now = new \DateTime();
        }

        $dates = [];

        foreach ($this->openingHours as $openingHour) {
            $timeRange = new TimeRange($openingHour);
            $dates[] = $timeRange->getNextOpeningDate($now);
        }

        sort($dates);

        return array_shift($dates);
    }

    /**
     * Return potential delivery times for a restaurant.
     *
     * We allow ordering at J+1.
     *
     * @param \DateTime|null $now
     * @return array
     */
    public function getAvailabilities(\DateTime $now = null) {

        if (!$now) {
            $now = new \DateTime();
        }

        $now->modify('+'.(self::ORDERING_DELAY + self::PREPARATION_AND_DELIVERY_DELAY).' minutes');

        $nextOpeningDate = $this->getNextOpeningDate($now);

        if (is_null($nextOpeningDate)) {
            return [];
        }

        $date =  clone $nextOpeningDate;

        $availabilities = [$date->format(\DateTime::ATOM)];

        $currentDay = $date->format('Ymd');
        $dayCount = 1;

        while ($dayCount <= self::NUMBER_OF_AVAILABLE_DAYS) {

            $date->modify('+15 minutes');

            $nextOpeningDate = $this->getNextOpeningDate($date);

            if (is_null($nextOpeningDate)) {
                return $availabilities;
            }

            $nextOpenedDate = clone $nextOpeningDate;

            $day = $nextOpenedDate->format('Ymd');

            if ($day !== $currentDay) {
                $currentDay = $day;
                $dayCount++;
            }
            else {
                $date = $nextOpenedDate;
                $availabilities[] = $date->format(\DateTime::ATOM);
            }
        }

        return $availabilities;
    }

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

    public function getStripeParams()
    {
        return $this->stripeParams;
    }

    public function setStripeParams(StripeParams $stripeParams)
    {
        $this->stripeParams = $stripeParams;

        return $this;
    }

    public function getDeliveryService()
    {
        return $this->deliveryService;
    }

    public function setDeliveryService($deliveryService)
    {
        $this->deliveryService = $deliveryService;

        return $this;
    }

    public function getHasMenu()
    {
        return $this->getMenu();
    }

    public function getMenu()
    {
        if (!$this->hasMenu) {
            $this->hasMenu = new Menu();
        }

        return $this->hasMenu;
    }

    public function setMenu(Menu $menu)
    {
        $this->hasMenu = $menu;
    }

    public function getMaxDistance()
    {
        return $this->maxDistance;
    }

    public function setMaxDistance($maxDistance)
    {
        $this->maxDistance = $maxDistance;

        return $this;
    }

    /**
     * @return Contract
     */
    public function getContract()
    {
        return $this->contract;
    }

    /**
     * @param Contract $contract
     */
    public function setContract(Contract $contract)
    {
        $this->contract = $contract;
        $contract->setRestaurant($this);
    }

    public function getFlatDeliveryPrice() {
        if ($this->contract) {
            return $this->contract->getFlatDeliveryPrice();
        }
    }

    public function getMinimumCartAmount() {
        if ($this->contract) {
            return $this->contract->getMinimumCartAmount();
        }
    }

    /**
     * Custom restaurant validation.
     * @Assert\Callback()
     */
    public function validate(ExecutionContextInterface $context, $payload)
    {

        $enabled = $this->isEnabled();

        $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
        $activationErrors = $validator->validate($this, null, ['activable']);
        $activationErrors = ValidationUtils::serializeValidationErrors($activationErrors);

        if ($enabled && count($activationErrors) > 0) {
            $context->buildViolation('Unable to activate restaurant.')
                ->atPath('enabled')
                ->addViolation();
        }

    }

}
