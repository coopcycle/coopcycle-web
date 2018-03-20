<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Base\FoodEstablishment;
use AppBundle\Filter\RestaurantFilter;
use AppBundle\Utils\ValidationUtils;
use AppBundle\Validator\Constraints as CustomAssert;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ArrayCollection;
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
 * @ApiResource(iri="http://schema.org/Restaurant",
 *   attributes={
 *     "filters"={RestaurantFilter::class},
 *     "denormalization_context"={"groups"={"order_create"}},
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
 * @CustomAssert\IsActivableRestaurant(groups="activable")
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
     * @Groups({"restaurant"})
     */
    private $id;

    /**
     * @var string The name of the item
     *
     * @Assert\Type(type="string")
     * @ApiProperty(iri="http://schema.org/name")
     * @Groups({"restaurant", "order"})
     */
    protected $name;

    /**
     * @var string The cuisine of the restaurant.
     *
     * @ApiProperty(iri="https://schema.org/servesCuisine")
     * @Groups({"restaurant"})
     */
    protected $servesCuisine;

    /**
     * @var boolean Is the restaurant enabled?
     *
     * A disable restaurant is not shown to visitors, either on search page or on its detail page.
     *
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
     * @var string
     */
    private $imageName;

    /**
     * @Groups({"restaurant"})
     */
    private $address;

    /**
     * @var string The website of the restaurant.
     *
     * @ApiProperty(iri="https://schema.org/URL")
     */
    private $website;

    /**
     * @var string The telephone number.
     *
     * @Assert\Type(type="string")
     */
    protected $telephone;

    /**
     * @var string The Stripe params of the restaurant.
     */
    private $stripeParams;

    /**
     * @var string The menu of the restaurant.
     *
     * @ApiProperty(iri="https://schema.org/Menu")
     * @Groups({"restaurant"})
     */
    private $hasMenu;

    private $maxDistance = 3000;

    private $closingRules;

    private $createdAt;

    private $updatedAt;

    /**
     * @var Contract
     * @Groups({"order_create"})
     */
    private $contract;

    public function __construct()
    {
        $this->servesCuisine = new ArrayCollection();
        $this->closingRules = new ArrayCollection();
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
     * @return mixed
     */
    public function getClosingRules()
    {
        return $this->closingRules;
    }

    /**
     * @param mixed $closingRules
     */
    public function setClosingRules($closingRules)
    {
        $this->closingRules = $closingRules;
    }

    public function hasClosingRuleForNow(\DateTime $now = null) {
        if (!$now) {
            $now = new \DateTime();
        }

        $criteria = Criteria::create()->where(Criteria::expr()->andX(
                Criteria::expr()->lte("startDate", $now),
                Criteria::expr()->gte("endDate", $now)
            ));

        return $this->closingRules->matching($criteria)->count() > 0;

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
                if (!$this->hasClosingRuleForNow($date)) {
                    $availabilities[] = $date->format(\DateTime::ATOM);
                }
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
}
