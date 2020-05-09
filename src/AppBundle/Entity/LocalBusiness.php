<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\MyRestaurants;
use AppBundle\Action\Restaurant\Close as CloseController;
use AppBundle\Action\Restaurant\Menu;
use AppBundle\Action\Restaurant\Menus;
use AppBundle\Annotation\Enabled;
use AppBundle\Api\Dto\RestaurantInput;
use AppBundle\Entity\Base\LocalBusiness as BaseLocalBusiness;
use AppBundle\Entity\LocalBusiness\CatalogTrait;
use AppBundle\Entity\LocalBusiness\FoodEstablishmentTrait;
use AppBundle\Entity\LocalBusiness\FulfillmentMethod;
use AppBundle\Entity\LocalBusiness\ImageTrait;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
use AppBundle\LoopEat\OAuthCredentialsTrait as LoopEatOAuthCredentialsTrait;
use AppBundle\Validator\Constraints as CustomAssert;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validation;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @ApiResource(
 *   shortName="Restaurant",
 *   attributes={
 *     "denormalization_context"={"groups"={"order_create", "restaurant_update"}},
 *     "normalization_context"={"groups"={"restaurant", "address", "order"}}
 *   },
 *   collectionOperations={
 *     "get"={
 *       "method"="GET",
 *       "pagination_enabled"=false
 *     },
 *     "me_restaurants"={
 *       "method"="GET",
 *       "path"="/me/restaurants",
 *       "controller"=MyRestaurants::class
 *     }
 *   },
 *   itemOperations={
 *     "get"={
 *       "method"="GET"
 *     },
 *     "restaurant_menu"={
 *       "method"="GET",
 *       "path"="/restaurants/{id}/menu",
 *       "controller"=Menu::class,
 *       "normalization_context"={"groups"={"restaurant_menu"}}
 *     },
 *     "restaurant_menus"={
 *       "method"="GET",
 *       "path"="/restaurants/{id}/menus",
 *       "controller"=Menus::class,
 *       "normalization_context"={"groups"={"restaurant_menus"}}
 *     },
 *     "put"={
 *       "method"="PUT",
 *       "input"=RestaurantInput::class,
 *       "denormalization_context"={"groups"={"restaurant_update"}},
 *       "access_control"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_RESTAURANT') and user.ownsRestaurant(object))"
 *     },
 *     "close"={
 *       "method"="PUT",
 *       "path"="/restaurants/{id}/close",
 *       "controller"=CloseController::class,
 *       "access_control"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_RESTAURANT') and user.ownsRestaurant(object))"
 *     }
 *   },
 *   subresourceOperations={
 *     "orders_get_subresource"={
 *       "security"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_RESTAURANT') and user.ownsRestaurant(object))"
 *     }
 *   }
 * )
 * @Vich\Uploadable
 * @CustomAssert\IsActivableRestaurant(groups="activable")
 * @Enabled
 */
class LocalBusiness extends BaseLocalBusiness
{
    use Timestampable;
    use SoftDeleteableEntity;
    use LoopEatOAuthCredentialsTrait;
    use CatalogTrait;
    use FoodEstablishmentTrait;
    use ImageTrait;

    /**
     * @var int
     * @Groups({"restaurant"})
     */
    protected $id;

    protected $type = FoodEstablishment::RESTAURANT;

    const STATE_NORMAL = 'normal';
    const STATE_RUSH = 'rush';
    const STATE_PLEDGE = 'pledge';

    /**
     * @var string The name of the item
     *
     * @Assert\Type(type="string")
     * @ApiProperty(iri="http://schema.org/name")
     * @Groups({"restaurant", "order", "restaurant_seo"})
     */
    protected $name;

    /**
     * @Groups({"restaurant"})
     */
    protected $description;

    /**
     * @var boolean Is the restaurant enabled?
     *
     * A disable restaurant is not shown to visitors, but remain accessible in preview to admins and owners.
     *
     * @Groups({"restaurant"})
     */
    protected $enabled = false;

    protected $quotesAllowed = false;

    /**
     * @var bool
     * @Groups({"restaurant"})
     */
    protected $depositRefundEnabled = false;

    /**
     * @var bool
     * @Groups({"restaurant"})
     */
    protected $depositRefundOptin = true;

    protected $loopeatEnabled = false;

    /**
     * @var integer Additional time to delay ordering
     */
    protected $orderingDelayMinutes = 0;

    /**
     * @Assert\GreaterThan(0)
     * @Assert\LessThanOrEqual(30)
     */
    protected $shippingOptionsDays = 2;

    protected $pledge;

    /**
     * @var Address
     *
     * @Groups({"restaurant", "order", "restaurant_seo"})
     */
    protected $address;

    /**
     * @var string The website of the restaurant.
     *
     * @ApiProperty(iri="https://schema.org/URL")
     */
    protected $website;

    protected $stripeAccounts;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    protected $deliveryPerimeterExpression = 'distance < 3000';

    /**
     * @Groups({"restaurant"})
     */
    protected $closingRules;

    protected $owners;

    protected $exclusive = false;

    /**
     * @Groups({"restaurant", "restaurant_update"})
     */
    protected $state = self::STATE_NORMAL;

    /**
     * @var Contract|null
     * @Groups({"order_create"})
     * @Assert\Valid(groups={"Default", "activable"})
     */
    protected $contract;

    /**
     * The roles needed to be able to manage Stripe Connect.
     */
    protected $stripeConnectRoles = ['ROLE_ADMIN'];

    protected $preparationTimeRules;

    protected $nextOpeningDateCache = [];

    protected $reusablePackagings;

    protected $promotions;

    protected $featured = false;

    protected $stripePaymentMethods = [];

    protected $fulfillmentMethods;

    public function __construct()
    {
        $this->servesCuisine = new ArrayCollection();
        $this->closingRules = new ArrayCollection();
        $this->owners = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->productOptions = new ArrayCollection();
        $this->taxons = new ArrayCollection();
        $this->orders = new ArrayCollection();
        $this->stripeAccounts = new ArrayCollection();
        $this->preparationTimeRules = new ArrayCollection();
        $this->reusablePackagings = new ArrayCollection();
        $this->promotions = new ArrayCollection();

        $this->fulfillmentMethods = new ArrayCollection();
        $this->addFulfillmentMethod('delivery');
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

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
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
     * @return mixed
     */
    public function getClosingRules()
    {
        return $this->closingRules;
    }

    /**
     * @param ClosingRule $closingRule
     */
    public function addClosingRule(ClosingRule $closingRule)
    {
        $this->closingRules->add($closingRule);
    }

    /**
     * @param \DateTime|null $now
     * @return boolean
     */
    public function hasClosingRuleForNow(\DateTime $now = null)
    {
        $closingRules = $this->getClosingRules();

        if (count($closingRules) === 0) {
            return false;
        }

        if (!$now) {
            $now = Carbon::now();
        }

        // WARNING
        // This method may be called a *lot* of times (see getAvailabilities)
        // Thus, we avoid using Criteria, because it would trigger a query every time
        foreach ($closingRules as $closingRule) {
            if ($now >= $closingRule->getStartDate() && $now <= $closingRule->getEndDate()) {
                return true;
            }
        }

        return false;
    }

    public function isOpen(\DateTime $now = null)
    {
        if (!$now) {
            $now = Carbon::now();
        }

        if ($this->hasClosingRuleForNow($now)) {

            return false;
        }

        return parent::isOpen($now);
    }

    public function getNextOpeningDate(\DateTime $now = null)
    {
        if (!$now) {
            $now = Carbon::now();
        }

        if (!isset($this->nextOpeningDateCache[$now->getTimestamp()])) {

            $nextOpeningDate = null;

            if ($this->hasClosingRuleForNow($now)) {
                foreach ($this->getClosingRules() as $closingRule) {
                    if ($now >= $closingRule->getStartDate() && $now <= $closingRule->getEndDate()) {

                        $nextOpeningDate = parent::getNextOpeningDate($closingRule->getEndDate());
                        break;
                    }
                }
            }

            if (null === $nextOpeningDate) {
                $nextOpeningDate = parent::getNextOpeningDate($now);
            }

            $this->nextOpeningDateCache[$now->getTimestamp()] = $nextOpeningDate;
        }

        return $this->nextOpeningDateCache[$now->getTimestamp()];
    }

    public function getNextClosingDate(\DateTime $now = null)
    {
        if (!$now) {
            $now = Carbon::now();
        }

        $nextClosingDates = [];
        if ($nextClosingDate = parent::getNextClosingDate($now)) {
            $nextClosingDates[] = $nextClosingDate;
        }

        foreach ($this->getClosingRules() as $closingRule) {
            if ($closingRule->getEndDate() < $now) {
                continue;
            }
            $nextClosingDates[] = $closingRule->getStartDate();
        }

        $nextClosingDates = array_filter($nextClosingDates, function (\DateTime $date) use ($now) {
            return $date >= $now;
        });

        sort($nextClosingDates);

        return array_shift($nextClosingDates);
    }

    /**
     * Return potential delivery times for a restaurant, pickables by the customer.
     * WARNING This function may be called a *LOT* of times, it needs to be *FAST*.
     *
     * @param \DateTime|null $now
     * @return array
     */
    public function getAvailabilities(\DateTime $now = null)
    {
        if (!$now) {
            $now = Carbon::now();
        }

        if ($this->getOrderingDelayMinutes() > 0) {
            $now->modify(sprintf('+%d minutes', $this->getOrderingDelayMinutes()));
        }

        $nextOpeningDate = $this->getNextOpeningDate($now);

        if (is_null($nextOpeningDate)) {
            return [];
        }

        $availabilities = [];

        $nextClosingDate = $this->getNextClosingDate($nextOpeningDate);

        $shippingOptionsDays = $this->shippingOptionsDays ?? 2;

        if ($shippingOptionsDays > 30) {
            $shippingOptionsDays = 30;
        }

        if (!$nextClosingDate) { // It is open 24/7
            $nextClosingDate = Carbon::instance($now)->add($shippingOptionsDays, 'days');

            $period = CarbonPeriod::create(
                $nextOpeningDate, '15 minutes', $nextClosingDate,
                CarbonPeriod::EXCLUDE_END_DATE
            );
            foreach ($period as $date) {
                $availabilities[] = $date->format(\DateTime::ATOM);
            }

            return $availabilities;
        }

        $numberOfDays = 0;
        $days = [];
        while ($numberOfDays < $shippingOptionsDays) {
            while (true) {

                $period = CarbonPeriod::create(
                    $nextOpeningDate, '15 minutes', $nextClosingDate,
                    CarbonPeriod::EXCLUDE_END_DATE
                );
                foreach ($period as $date) {
                    $availabilities[] = $date->format(\DateTime::ATOM);
                    $days[] = $date->format('Y-m-d');
                    $numberOfDays = count(array_unique($days));
                }

                $nextOpeningDate = $this->getNextOpeningDate($nextClosingDate);

                if (!Carbon::instance($nextOpeningDate)->isSameDay($nextClosingDate)) {
                    $nextClosingDate = $this->getNextClosingDate($nextOpeningDate);
                    break;
                }

                $nextClosingDate = $this->getNextClosingDate($nextOpeningDate);
            }
        }

        return $availabilities;
    }

    public function getStripeAccounts()
    {
        return $this->stripeAccounts;
    }

    public function addStripeAccount(StripeAccount $stripeAccount)
    {
        $restaurantStripeAccount = new RestaurantStripeAccount();
        $restaurantStripeAccount->setRestaurant($this);
        $restaurantStripeAccount->setStripeAccount($stripeAccount);
        $restaurantStripeAccount->setLivemode($stripeAccount->getLivemode());

        $this->stripeAccounts->add($restaurantStripeAccount);
    }

    public function getStripeAccount($livemode)
    {
        foreach ($this->getStripeAccounts() as $stripeAccount) {
            if ($stripeAccount->isLivemode() === $livemode) {
                return $stripeAccount->getStripeAccount();
            }
        }
    }

    /**
     * @return string
     */
    public function getDeliveryPerimeterExpression()
    {
        return $this->deliveryPerimeterExpression;
    }

    /**
     * @param string $deliveryPerimeterExpression
     */
    public function setDeliveryPerimeterExpression(string $deliveryPerimeterExpression)
    {
        $this->deliveryPerimeterExpression = $deliveryPerimeterExpression;
    }

    /**
     * @return int
     */
    public function getOrderingDelayMinutes()
    {
        return $this->orderingDelayMinutes;
    }

    /**
     * @param int $orderingDelayMinutes
     */
    public function setOrderingDelayMinutes(int $orderingDelayMinutes)
    {
        $this->orderingDelayMinutes = $orderingDelayMinutes;
    }

    /**
     * @return int
     */
    public function getShippingOptionsDays()
    {
        return $this->shippingOptionsDays;
    }

    /**
     * @param int $shippingOptionsDays
     */
    public function setShippingOptionsDays(int $shippingOptionsDays)
    {
        $this->shippingOptionsDays = $shippingOptionsDays;
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

    public function getOwners()
    {
        return $this->owners;
    }

    public function canDeliverAddress(Address $address, $distance, ExpressionLanguage $language = null)
    {
        if (null === $language) {
            $language = new ExpressionLanguage();
        }

        $dropoff = new \stdClass();
        $dropoff->address = $address;

        return $language->evaluate($this->deliveryPerimeterExpression, [
            'distance' => $distance,
            'dropoff' => $dropoff,
        ]);
    }

    public function getState()
    {
        return $this->state;
    }

    public function setState($state)
    {
        $this->state = $state;

        return $this;
    }

    public function getStripeConnectRoles()
    {
        return $this->stripeConnectRoles;
    }

    public function setStripeConnectRoles($stripeConnectRoles)
    {
        $this->stripeConnectRoles = $stripeConnectRoles;

        return $this;
    }

    public function getPreparationTimeRules()
    {
        return $this->preparationTimeRules;
    }

    public function setPreparationTimeRules($preparationTimeRules)
    {
        $this->preparationTimeRules->clear();

        foreach ($preparationTimeRules as $preparationTimeRule) {
            $this->addPreparationTimeRule($preparationTimeRule);
        }

        return $this;
    }

    public function addPreparationTimeRule($preparationTimeRule)
    {
        $preparationTimeRule->setRestaurant($this);

        $this->preparationTimeRules->add($preparationTimeRule);

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPledge()
    {
        return $this->pledge;
    }

    /**
     * @param mixed $pledge
     *
     * @return self
     */
    public function setPledge($pledge)
    {
        $pledge->setRestaurant($this);

        $this->pledge = $pledge;

        return $this;
    }

    /**
     * @return bool
     */
    public function isExclusive(): bool
    {
        return $this->exclusive;
    }

    /**
     * @param bool $exclusive
     */
    public function setExclusive(bool $exclusive)
    {
        $this->exclusive = $exclusive;

        return $this;
    }

    /**
     * @return mixed
     */
    public function isDepositRefundEnabled()
    {
        return $this->depositRefundEnabled;
    }

    /**
     * @param mixed $depositRefundEnabled
     *
     * @return self
     */
    public function setDepositRefundEnabled($depositRefundEnabled)
    {
        $this->depositRefundEnabled = $depositRefundEnabled;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDepositRefundOptin(): bool
    {
        if ($this->isLoopeatEnabled()) {

            return true;
        }

        return $this->depositRefundOptin;
    }

    /**
     * @param bool $depositRefundOptin
     *
     * @return self
     */
    public function setDepositRefundOptin(bool $depositRefundOptin)
    {
        $this->depositRefundOptin = $depositRefundOptin;

        return $this;
    }

    /**
     * @return mixed
     */
    public function isQuotesAllowed()
    {
        return $this->quotesAllowed;
    }

    /**
     * @param mixed $quotesAllowed
     *
     * @return self
     */
    public function setQuotesAllowed($quotesAllowed)
    {
        $this->quotesAllowed = $quotesAllowed;

        return $this;
    }

    public function getReusablePackagings()
    {
        return $this->reusablePackagings;
    }

    /**
     * @param mixed $reusablePackagings
     *
     * @return self
     */
    public function setReusablePackagings($reusablePackagings)
    {
        $this->reusablePackagings = $reusablePackagings;

        return $this;
    }

    /**
     * @param ReusablePackaging $reusablePackaging
     *
     * @return self
     */
    public function addReusablePackaging(ReusablePackaging $reusablePackaging)
    {
        $reusablePackaging->setRestaurant($this);

        $this->reusablePackagings->add($reusablePackaging);

        return $this;
    }

    /**
     * @param ReusablePackaging $reusablePackaging
     *
     * @return bool
     */
    public function hasReusablePackaging(ReusablePackaging $reusablePackaging)
    {
        return $this->reusablePackagings->contains($reusablePackaging);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasReusablePackagingWithName(string $name)
    {
        foreach ($this->reusablePackagings as $reusablePackaging) {
            if ($reusablePackaging->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isLoopeatEnabled()
    {
        return $this->loopeatEnabled;
    }

    /**
     * @param bool $loopeatEnabled
     *
     * @return self
     */
    public function setLoopeatEnabled($loopeatEnabled)
    {
        $this->loopeatEnabled = $loopeatEnabled;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getContext()
    {
        if ($found = Store::search($this->type)) {
            return Store::class;
        }

        return FoodEstablishment::class;
    }

    public function getOpeningHoursBehavior($method = 'delivery')
    {
        foreach ($this->getFulfillmentMethods() as $fulfillmentMethod) {
            if ($method === $fulfillmentMethod->getType()) {

                return $fulfillmentMethod->getOpeningHoursBehavior();
            }
        }

        return 'asap';
    }

    public function setOpeningHoursBehavior($openingHoursBehavior, $method = 'delivery')
    {
        foreach ($this->getFulfillmentMethods() as $fulfillmentMethod) {
            if ($method === $fulfillmentMethod->getType()) {

                return $fulfillmentMethod->setOpeningHoursBehavior($openingHoursBehavior);
            }
        }
    }

    public function addPromotion($promotion)
    {
        if (!$this->promotions->contains($promotion)) {
            $this->promotions->add($promotion);
        }
    }

    public function getPromotions()
    {
        return $this->promotions;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured)
    {
        $this->featured = $featured;
    }

    /**
     * @param string $paymentMethod
     */
    public function enableStripePaymentMethod($paymentMethod)
    {
        $paymentMethods = $this->stripePaymentMethods;

        $paymentMethods[] = $paymentMethod;

        $this->stripePaymentMethods = array_unique($paymentMethods);
    }

    /**
     * @param string $paymentMethod
     */
    public function disableStripePaymentMethod($paymentMethod)
    {
        $this->stripePaymentMethods = array_filter($this->stripePaymentMethods, function ($method) use ($paymentMethod) {
            return $method !== $paymentMethod;
        });
    }

    /**
     * @param string $paymentMethod
     */
    public function isStripePaymentMethodEnabled($paymentMethod)
    {
        return in_array($paymentMethod, $this->stripePaymentMethods);
    }

    public function isTakeawayEnabled(): bool
    {
        foreach ($this->getFulfillmentMethods() as $fulfillmentMethod) {
            if ('collection' === $fulfillmentMethod->getType()) {

                return $fulfillmentMethod->isEnabled();
            }
        }

        return false;
    }

    public function setTakeawayEnabled(bool $takeawayEnabled)
    {
        $collectionMethod = $this->fulfillmentMethods->filter(function (FulfillmentMethod $fulfillmentMethod): bool {
            return 'collection' === $fulfillmentMethod->getType();
        })->first();

        if (!$collectionMethod) {

            $collectionMethod = new FulfillmentMethod();
            $collectionMethod->setRestaurant($this);
            $collectionMethod->setType('collection');

            $this->fulfillmentMethods->add($collectionMethod);
        }

        $collectionMethod->setEnabled($takeawayEnabled);
    }

    public function getFulfillmentMethods()
    {
        return $this->fulfillmentMethods;
    }

    public function addFulfillmentMethod($method)
    {
        $exists = false;
        foreach ($this->getFulfillmentMethods() as $fulfillmentMethod) {
            if ($method === $fulfillmentMethod->getType()) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $fulfillmentMethod = new FulfillmentMethod();
            $fulfillmentMethod->setRestaurant($this);
            $fulfillmentMethod->setType($method);

            $this->fulfillmentMethods->add($fulfillmentMethod);
        }
    }

    public function getOpeningHours($method = 'delivery')
    {
        foreach ($this->getFulfillmentMethods() as $fulfillmentMethod) {
            if ($method === $fulfillmentMethod->getType()) {

                return $fulfillmentMethod->getOpeningHours();
            }
        }

        return [];
    }

    public function setOpeningHours($openingHours, $method = 'delivery')
    {
        foreach ($this->getFulfillmentMethods() as $fulfillmentMethod) {
            if ($method === $fulfillmentMethod->getType()) {
                $fulfillmentMethod->setOpeningHours($openingHours);

                break;
            }
        }

        return $this;
    }

    public function addOpeningHour($openingHour, $method = 'delivery')
    {
        foreach ($this->getFulfillmentMethods() as $fulfillmentMethod) {
            if ($method === $fulfillmentMethod->getType()) {
                $fulfillmentMethod->addOpeningHour($openingHour);

                break;
            }
        }

        return $this;
    }
}
