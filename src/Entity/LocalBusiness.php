<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\MyRestaurants;
use AppBundle\Action\Restaurant\Close as CloseController;
use AppBundle\Action\Restaurant\Menu;
use AppBundle\Action\Restaurant\Deliveries as RestaurantDeliveriesController;
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
use AppBundle\Entity\Model\OrganizationAwareInterface;
use AppBundle\Entity\Model\OrganizationAwareTrait;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
use AppBundle\LoopEat\OAuthCredentialsTrait as LoopEatOAuthCredentialsTrait;
use AppBundle\OpeningHours\OpenCloseInterface;
use AppBundle\OpeningHours\OpenCloseTrait;
use AppBundle\Sylius\Product\ProductInterface;
use AppBundle\Validator\Constraints\IsActivableRestaurant as AssertIsActivableRestaurant;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
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
 *       "normalization_context"={"groups"={"restaurant", "address", "order"}}
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
    ShippingOptionsInterface
{
    use Timestampable;
    use SoftDeleteableEntity;
    use LoopEatOAuthCredentialsTrait;
    use CatalogTrait;
    use FoodEstablishmentTrait;
    use ImageTrait;
    use OpenCloseTrait;
    use OrganizationAwareTrait;
    use ClosingRulesTrait;
    use FulfillmentMethodsTrait;
    use ShippingOptionsTrait;

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
     * @Groups({"restaurant", "order", "restaurant_seo", "restaurant_simple"})
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

    protected $pledge;

    /**
     * @var Address
     *
     * @Groups({"restaurant", "order", "restaurant_seo", "restaurant_simple"})
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
    protected $stripeConnectRoles = ['ROLE_ADMIN'];

    protected $preparationTimeRules;

    protected $reusablePackagings;

    protected $promotions;

    protected $featured = false;

    protected $stripePaymentMethods = [];

    /**
     * @Groups({"restaurant"})
     */
    protected $isAvailableForB2b;

    protected $mercadopagoAccounts;

    protected $edenredMerchantId;

    protected $hub;

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
        $this->isAvailableForB2b = false ;
        $this->mercadopagoAccounts = new ArrayCollection();

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

    /**
     * @return bool
     */
    public function isAvailableForB2b(): bool
    {
        return $this->isAvailableForB2b;
    }

    /**
     * @param bool $isAvailableForB2b
     */
    public function setIsAvailableForB2b(bool $isAvailableForB2b): void
    {
        $this->isAvailableForB2b = $isAvailableForB2b;
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

    public function getMercadopagoAccounts()
    {
        return $this->mercadopagoAccounts;
    }

    public function addMercadopagoAccount(MercadopagoAccount $account)
    {
        $manyToMany = new RestaurantMercadopagoAccount();
        $manyToMany->setRestaurant($this);
        $manyToMany->setMercadopagoAccount($account);
        $manyToMany->setLivemode($account->getLivemode());

        $this->mercadopagoAccounts->add($manyToMany);
    }

    public function getMercadopagoAccount($livemode): ?MercadopagoAccount
    {
        foreach ($this->getMercadopagoAccounts() as $account) {
            if ($account->isLivemode() === $livemode) {
                return $account->getMercadopagoAccount();
            }
        }

        return null;
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
}
