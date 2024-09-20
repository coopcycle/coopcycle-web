<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\MyRestaurants;
use AppBundle\Action\Restaurant\Close as CloseController;
use AppBundle\Action\Restaurant\Menu;
use AppBundle\Action\Restaurant\Deliveries as RestaurantDeliveriesController;
use AppBundle\Action\Restaurant\ReusablePackagings;
use AppBundle\Action\Restaurant\Menus;
use AppBundle\Action\Restaurant\Orders;
use AppBundle\Action\Restaurant\Timing;
use AppBundle\Api\Dto\RestaurantInput;
use AppBundle\Entity\Base\LocalBusiness as BaseLocalBusiness;
use AppBundle\Entity\LocalBusiness\CatalogInterface;
use AppBundle\Entity\LocalBusiness\CatalogTrait;
use AppBundle\Entity\LocalBusiness\ClosingRulesTrait;
use AppBundle\Entity\LocalBusiness\FoodEstablishmentTrait;
use AppBundle\Entity\LocalBusiness\FulfillmentMethod;
use AppBundle\Entity\LocalBusiness\FulfillmentMethodsTrait;
use AppBundle\Entity\LocalBusiness\ImageTrait;
use AppBundle\Entity\LocalBusiness\ShippingOptionsInterface;
use AppBundle\Entity\LocalBusiness\ShippingOptionsTrait;
use AppBundle\Entity\Model\CustomFailureReasonInterface;
use AppBundle\Entity\Model\CustomFailureReasonTrait;
use AppBundle\Entity\Model\OrganizationAwareInterface;
use AppBundle\Entity\Model\OrganizationAwareTrait;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
use AppBundle\LoopEat\OAuthCredentialsTrait as LoopEatOAuthCredentialsTrait;
use AppBundle\OpeningHours\OpenCloseInterface;
use AppBundle\OpeningHours\OpenCloseTrait;
use AppBundle\Sylius\Product\ProductInterface;
use AppBundle\Validator\Constraints\IsActivableRestaurant as AssertIsActivableRestaurant;
use Cocur\Slugify\Slugify;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Gedmo\SoftDeleteable\Traits\SoftDeleteable;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
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
 *       "pagination_enabled"=false,
 *       "normalization_context"={"groups"={"restaurant", "address", "order", "restaurant_list"}}
 *     },
 *     "me_restaurants"={
 *       "method"="GET",
 *       "path"="/me/restaurants",
 *       "controller"=MyRestaurants::class
 *     }
 *   },
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "normalization_context"={"groups"={"restaurant", "address", "order", "restaurant_potential_action"}},
 *       "security"="is_granted('view', object)"
 *     },
 *     "delete"={
 *       "method"="DELETE",
 *       "security"="is_granted('ROLE_ADMIN')"
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
 *       "security"="is_granted('edit', object)"
 *     },
 *     "close"={
 *       "method"="PUT",
 *       "path"="/restaurants/{id}/close",
 *       "controller"=CloseController::class,
 *       "security"="is_granted('edit', object)"
 *     },
 *     "restaurant_deliveries"={
 *       "method"="GET",
 *       "path"="/restaurants/{id}/deliveries/{date}",
 *       "controller"=RestaurantDeliveriesController::class,
 *       "access_control"="is_granted('ROLE_ADMIN')",
 *       "normalization_context"={"groups"={"delivery", "address", "restaurant_delivery"}}
 *     },
 *     "restaurant_timing"={
 *       "method"="GET",
 *       "path"="/restaurants/{id}/timing",
 *       "controller"=Timing::class,
 *       "normalization_context"={"groups"={"restaurant_timing"}}
 *     },
 *     "restaurant_orders"={
 *       "method"="GET",
 *       "path"="/restaurants/{id}/orders",
 *       "controller"=Orders::class,
 *       "security"="is_granted('edit', object)"
 *     }
 *   }
 * )
 * @Vich\Uploadable
 * @AssertIsActivableRestaurant(groups="activable")
 */
class LocalBusiness extends BaseLocalBusiness implements
    CatalogInterface,
    OpenCloseInterface,
    OrganizationAwareInterface,
    ShippingOptionsInterface,
    CustomFailureReasonInterface,
    Vendor
{
    use Timestampable;
    use SoftDeleteable;
    use LoopEatOAuthCredentialsTrait;
    use CatalogTrait;
    use FoodEstablishmentTrait;
    use ImageTrait;
    use OpenCloseTrait;
    use OrganizationAwareTrait;
    use ClosingRulesTrait;
    use FulfillmentMethodsTrait;
    use ShippingOptionsTrait;
    use CustomFailureReasonTrait;

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
     * @Groups({"restaurant", "order", "restaurant_seo", "restaurant_simple", "order", "order_minimal"})
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

    /**
     * @var bool
     * @Groups({"order"})
     */
    protected $loopeatEnabled = false;

    protected $pledge;

    /**
     * @var Address
     *
     * @Groups({"restaurant", "order", "restaurant_seo", "restaurant_simple", "order", "order_minimal"})
     */
    protected $address;

    /**
     * @var Address|null
     */
    protected $businessAddress;

    /**
     * @var string The website of the restaurant.
     *
     * @ApiProperty(iri="https://schema.org/URL")
     */
    protected $website;

    protected $stripeAccounts;

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
    protected array $stripeConnectRoles = ['ROLE_ADMIN'];

    /**
     * The roles needed to be able to manage Mercadopago connect.
     */
    protected array $mercadopagoConnectRoles = ['ROLE_ADMIN'];

    protected $preparationTimeRules;

    protected $reusablePackagings;

    protected $promotions;

    protected $featured = false;

    protected array $stripePaymentMethods = [];

    protected $mercadopagoAccount;

    /**
     * @Groups({"restaurant"})
     */
    protected $edenredMerchantId;

    /**
     * @Groups({"restaurant"})
     */
    protected $edenredTRCardEnabled = false;

    /**
     * @Groups({"restaurant"})
     */
    protected $edenredEnabled = false;

    /**
     * @Groups({"restaurant"})
     */
    protected $edenredSyncSent = false;

    /**
     * @Groups({"restaurant"})
     */
    protected $hub;

    protected $vytalEnabled = false;

    protected $enBoitLePlatEnabled = false;

    protected $cashOnDeliveryEnabled = false;

    protected $dabbaEnabled = false;

    protected $dabbaCode;


    protected ?int $rateLimitRangeDuration;

    protected ?int $rateLimitAmount;

    protected ?string $paygreenShopId = null;

    protected string $billingMethod = 'unit';

    /**
     * @Groups({"restaurant"})
     */
    protected bool $autoAcceptOrdersEnabled = false;

    public function __construct()
    {
        $this->servesCuisine = new ArrayCollection();
        $this->closingRules = new ArrayCollection();
        $this->owners = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->productOptions = new ArrayCollection();
        $this->taxons = new ArrayCollection();
        $this->stripeAccounts = new ArrayCollection();
        $this->preparationTimeRules = new ArrayCollection();
        $this->reusablePackagings = new ArrayCollection();
        $this->promotions = new ArrayCollection();

        $this->fulfillmentMethods = new ArrayCollection();
        $this->addFulfillmentMethod('delivery', true);
        $this->addFulfillmentMethod('collection', false);
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

    public function getBusinessAddress($fallback = false)
    {
        if ($fallback) {
            return $this->businessAddress ?? $this->address;
        }

        return $this->businessAddress;
    }

    public function setBusinessAddress(?Address $address)
    {
        $this->businessAddress = $address;
    }

    public function hasDifferentBusinessAddress()
    {
        return $this->businessAddress !== null;
    }

    public function setAddress(Address $address)
    {
        $this->address = $address;

        return $this;
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
    }

    public function getOwners(): Collection
    {
        return $this->owners;
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

    public function getMercadopagoConnectRoles()
    {
        return $this->mercadopagoConnectRoles;
    }

    public function setMercadopagoConnectRoles($mercadopagoConnectRoles)
    {
        $this->mercadopagoConnectRoles = $mercadopagoConnectRoles;

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
        if ($this->isLoopeatEnabled() || $this->isDabbaEnabled()) {

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

    /**
     * @deprecated
     */
    public function isTakeawayEnabled(): bool
    {
        return $this->isFulfillmentMethodEnabled('collection');
    }

    /**
     * @deprecated
     */
    public function setTakeawayEnabled(bool $takeawayEnabled)
    {
        $this->addFulfillmentMethod('collection', $takeawayEnabled);
    }

    public function setMinimumAmount($method, $amount)
    {
        $fulfillmentMethod = $this->getFulfillmentMethod($method);
        if ($fulfillmentMethod) {
            $fulfillmentMethod->setMinimumAmount($amount);
        }
    }

    public function addOwner(User $owner)
    {
        $owner->addRestaurant($this);

        $this->owners->add($owner);
    }

    public function getMercadopagoAccount(): ?MercadopagoAccount
    {
        return $this->mercadopagoAccount;
    }

    public function setMercadopagoAccount(?MercadopagoAccount $account)
    {
        $this->mercadopagoAccount = $account;
    }

    public function asOriginCode(): string
    {
        return (string) $this->getId();
    }

    public function getEdenredMerchantId()
    {
        return $this->edenredMerchantId;
    }

    public function setEdenredMerchantId($edenredMerchantId)
    {
        $this->edenredMerchantId = $edenredMerchantId;
    }

    public function isEdenredEnabled()
    {
        return $this->edenredEnabled;
    }

    public function setEdenredEnabled($edenredEnabled)
    {
        $this->edenredEnabled = $edenredEnabled;
    }

    public function isEdenredTRCardEnabled()
    {
        return $this->edenredTRCardEnabled;
    }

    public function setEdenredTRCardEnabled($edenredTRCardEnabled)
    {
        $this->edenredTRCardEnabled = $edenredTRCardEnabled;
    }

    public function getEdenredSyncSent()
    {
        return $this->edenredSyncSent;
    }

    public function setEdenredSyncSent($edenredSyncSent)
    {
        $this->edenredSyncSent = $edenredSyncSent;
    }

    public function supportsEdenred(): bool
    {
        return $this->edenredEnabled && null !== $this->getEdenredMerchantId();
    }

    public function getHub(): ?Hub
    {
        return $this->hub;
    }

    public function setHub(?Hub $hub)
    {
        $this->hub = $hub;
    }

    public function belongsToHub(): bool
    {
        return null !== $this->hub;
    }

    /**
     * @return bool
     */
    public function isVytalEnabled()
    {
        return $this->vytalEnabled;
    }

    /**
     * @param bool $enabled
     *
     * @return self
     */
    public function setVytalEnabled($enabled)
    {
        $this->vytalEnabled = $enabled;

        return $this;
    }

    public static function getKeyForType($type)
    {
        $slugify = new Slugify();

        if (Store::isValid($type)) {
            foreach (Store::values() as $value) {
                if ($value->getValue() === $type) {

                    return $slugify->slugify($value->getKey());
                }
            }
        }

        foreach (FoodEstablishment::values() as $value) {
            if ($value->getValue() === $type) {

                return $slugify->slugify($value->getKey());
            }
        }
    }

    public static function getTransKeyForType($type)
    {
        if (Store::isValid($type)) {
            foreach (Store::values() as $value) {
                if ($value->getValue() === $type) {

                    return sprintf('store.%s', $value->getKey());
                }
            }
        }

        foreach (FoodEstablishment::values() as $value) {
            if ($value->getValue() === $type) {

                return sprintf('food_establishment.%s', $value->getKey());
            }
        }
    }

    public static function getTypeForKey($key)
    {
        $slugify = new Slugify();

        foreach (Store::values() as $value) {
            $typesByKey[$slugify->slugify($value->getKey())] = $value->getValue();
        }

        foreach (FoodEstablishment::values() as $value) {
            $typesByKey[$slugify->slugify($value->getKey())] = $value->getValue();
        }

        return $typesByKey[$key] ?? null;
    }

    /**
     * @return bool
     */
    public function isCashOnDeliveryEnabled()
    {
        return $this->cashOnDeliveryEnabled;
    }

    /**
     * @param bool $enabled
     *
     * @return self
     */
    public function setCashOnDeliveryEnabled($enabled)
    {
        $this->cashOnDeliveryEnabled = $enabled;

        return $this;
    }

    /**
     * @return bool
     */
    public function isEnBoitLePlatEnabled()
    {
        return $this->enBoitLePlatEnabled;
    }

    /**
     * @param bool $enabled
     *
     * @return self
     */
    public function setEnBoitLePlatEnabled($enabled)
    {
        $this->enBoitLePlatEnabled = $enabled;

        return $this;
    }

    /**
     * @SerializedName("facets")
     * @Groups("restaurant_list")
     */
    public function getFacets()
    {
        $facets = [
            'category' => [],
            'cuisine'  => [],
            'type'     => [],
        ];

        if ($this->isExclusive()) {
            $facets['category'][] = 'exclusive';
        }

        if ($this->isFeatured()) {
            $facets['category'][] = 'featured';
        }

        if ($this->isZeroWaste()) {
            $facets['category'][] = 'zero_waste';
        }

        foreach ($this->getServesCuisine() as $cuisine) {
            $facets['cuisine'][] = $cuisine->getName();
        }

        $facets['type'] = $this->getType();

        return $facets;
    }

    public function isZeroWaste()
    {
        return $this->isDepositRefundEnabled() || $this->isLoopeatEnabled() || $this->isDabbaEnabled();
    }

    /**
     * @return bool
     */
    public function isDabbaEnabled()
    {
        return $this->dabbaEnabled;
    }

    /**
     * @param bool $enabled
     *
     * @return self
     */
    public function setDabbaEnabled($enabled)
    {
        $this->dabbaEnabled = $enabled;

        return $this;
    }

    public function getDabbaCode()
    {
        return $this->dabbaCode;
    }

    public function setDabbaCode($dabbaCode)
    {
        $this->dabbaCode = $dabbaCode;
    }

    public function getShopCuisines()
    {
        $isFoodEstablishment = FoodEstablishment::isValid($this->getType());

        if (!$isFoodEstablishment) {
            return [];
        }

        $cuisines = [];
        foreach($this->getServesCuisine() as $c) {
            $cuisines[] = $c->getName();
        }

        return $cuisines;
    }

    public function getShopCategories()
    {
        $categories = [];

        if ($this->isFeatured()) {
            $categories[] = 'featured';
        }

        if ($this->isExclusive()) {
            $categories[] = 'exclusive';
        }

        if ($this->isDepositRefundEnabled() || $this->isLoopeatEnabled()) {
            $categories[] = 'zerowaste';
        }

        return $categories;
    }

    public function getShopType()
    {
        return self::getKeyForType($this->getType());
    }

    /**
     * @return int|null
     */
    public function getRateLimitRangeDuration(): ?int
    {
        return $this->rateLimitRangeDuration;
    }

    /**
     * @param int|null $rateLimitRangeDuration
     * @return LocalBusiness
     */
    public function setRateLimitRangeDuration(?int $rateLimitRangeDuration): LocalBusiness
    {
        $this->rateLimitRangeDuration = $rateLimitRangeDuration;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getRateLimitAmount(): ?int
    {
        return $this->rateLimitAmount;
    }

    /**
     * @param int|null $rateLimitAmount
     * @return LocalBusiness
     */
    public function setRateLimitAmount(?int $rateLimitAmount): LocalBusiness
    {
        $this->rateLimitAmount = $rateLimitAmount;
        return $this;
    }

    /**
     * @param string $expression
     * @return $this
     */
    public function setOrdersRateLimiter(string $expression): LocalBusiness
    {
        [$amount, $timeWindow] = explode(':', $expression);

        if (!empty($amount) && !empty($timeWindow)) {
            $this->setRateLimitAmount(intval($amount));
            $this->setRateLimitRangeDuration(intval($timeWindow));
            return $this;
        }

        $this->setRateLimitAmount(null);
        $this->setRateLimitRangeDuration(null);
        return $this;
    }

    /**
     * @return string
     */
    public function getOrdersRateLimiter(): string
    {
        return sprintf('%s:%s',
            $this->getRateLimitAmount(),
            $this->getRateLimitRangeDuration()
        );
    }

    public function isAutoAcceptOrdersEnabled(): bool
    {
        return $this->autoAcceptOrdersEnabled;
    }

    public function setAutoAcceptOrdersEnabled(bool $enabled): void
    {
        $this->autoAcceptOrdersEnabled = $enabled;
    }

    public function setPaygreenShopId(string $shopId): void
    {
        $this->paygreenShopId = $shopId;
    }

    public function getPaygreenShopId(): ?string
    {
        return $this->paygreenShopId;
    }

    public function supportsPaygreen(): bool
    {
        return null !== $this->getPaygreenShopId();
    }

    public function setBillingMethod(string $billingMethod): void
    {
        $this->billingMethod = $billingMethod;
    }

    public function getBillingMethod(): string
    {
        return $this->billingMethod;
    }
}
