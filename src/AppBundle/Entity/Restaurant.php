<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiSubresource;
use AppBundle\Action\MyRestaurants;
use AppBundle\Action\Restaurant\Close as CloseRestaurant;
use AppBundle\Action\Restaurant\Menu;
use AppBundle\Action\Restaurant\Menus;
use AppBundle\Annotation\Enabled;
use AppBundle\Api\Controller\Restaurant\ChangeState;
use AppBundle\Api\Dto\RestaurantInput;
use AppBundle\Entity\Base\FoodEstablishment;
use AppBundle\Validator\Constraints as CustomAssert;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
 *     "denormalization_context"={"groups"={"order_create", "restaurant_update"}},
 *     "normalization_context"={"groups"={"restaurant", "place", "order"}}
 *   },
 *   collectionOperations={
 *     "get"={"method"="GET"},
 *     "me_restaurants"={
 *       "method"="GET",
 *       "path"="/me/restaurants",
 *       "controller"=MyRestaurants::class
 *     }
 *   },
 *   itemOperations={
 *     "get"={"method"="GET"},
 *     "restaurant_menu"={
 *       "method"="GET",
 *       "path"="/restaurants/{id}/menu",
 *       "controller"=Menu::class,
 *       "normalization_context"={"groups"={"restaurant_menu"}},
 *     },
 *     "restaurant_menus"={
 *       "method"="GET",
 *       "path"="/restaurants/{id}/menus",
 *       "controller"=Menus::class,
 *       "normalization_context"={"groups"={"restaurant_menus"}},
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
 *       "controller"=CloseRestaurant::class,
 *       "access_control"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_RESTAURANT') and user.ownsRestaurant(object))",
 *     }
 *   },
 *   subresourceOperations={
 *     "orders_get_subresource"={
 *       "security"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_RESTAURANT') and user.ownsRestaurant(object))"
 *     },
 *   },
 * )
 * @Vich\Uploadable
 * @CustomAssert\IsActivableRestaurant(groups="activable")
 * @Enabled
 */
class Restaurant extends FoodEstablishment
{
    use SoftDeleteableEntity;

    const STATE_NORMAL = 'normal';
    const STATE_RUSH = 'rush';

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
     * @Groups({"restaurant", "order", "restaurant_seo"})
     */
    protected $name;

    protected $description;

    /**
     * @var mixed The cuisine of the restaurant.
     *
     * @ApiProperty(iri="https://schema.org/servesCuisine")
     * @Groups({"restaurant"})
     */
    protected $servesCuisine;

    /**
     * @var boolean Is the restaurant enabled?
     *
     * A disable restaurant is not shown to visitors, but remain accessible in preview to admins and owners.
     *
     * @Groups({"restaurant"})
     *
     */
    protected $enabled = false;

    protected $quotesAllowed = false;

    protected $depositRefundEnabled = false;

    protected $depositRefundOptin = true;

    /**
     * @var integer Additional time to delay ordering
     *
     */
    protected $orderingDelayMinutes = 0;

    protected $pledge;

    /**
     * @Vich\UploadableField(mapping="restaurant_image", fileNameProperty="imageName")
     * @Assert\File(
     *   maxSize = "1024k",
     *   mimeTypes = {"image/jpg", "image/jpeg", "image/png"}
     * )
     * @var File
     */
    private $imageFile;

    /**
     * @var string
     */
    private $imageName;

    /**
     * @var Address
     *
     * @Groups({"restaurant", "order", "restaurant_seo"})
     */
    private $address;

    /**
     * @var string The website of the restaurant.
     *
     * @ApiProperty(iri="https://schema.org/URL")
     */
    private $website;

    private $stripeAccounts;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    private $deliveryPerimeterExpression = 'distance < 3000';

    /**
     * @Groups({"restaurant"})
     */
    private $closingRules;

    private $createdAt;

    private $updatedAt;

    private $owners;

    /**
     * @ApiSubresource
     */
    private $products;

    private $productOptions;

    private $taxons;

    /**
     * @Groups({"restaurant"})
     */
    private $activeMenuTaxon;

    private $exclusive = false;

    /**
     * @Groups({"restaurant", "restaurant_update"})
     */
    private $state = self::STATE_NORMAL;

    /**
     * @var Contract|null
     * @Groups({"order_create"})
     */
    private $contract;

    /**
     * @ApiSubresource
     */
    private $orders;

    /**
     * The roles needed to be able to manage Stripe Connect.
     */
    private $stripeConnectRoles = ['ROLE_ADMIN'];

    private $preparationTimeRules;

    private $nextOpeningDateCache = [];

    private $reusablePackagings;

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
     * @param File|UploadedFile|null $image
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

        // We return times for the next 2 days
        // There used to be a constant NUMBER_OF_AVAILABLE_DAYS = 2
        // As the interface doesn't allow passing a parameter,
        // we don't care about using a loop

        $availabilities = [];

        $nextClosingDate = $this->getNextClosingDate($nextOpeningDate);

        if (!$nextClosingDate) { // It is open 24/7
            $period = CarbonPeriod::create(
                $nextOpeningDate, '15 minutes', Carbon::instance($now)->add(2, 'days'),
                CarbonPeriod::EXCLUDE_END_DATE
            );
            foreach ($period as $date) {
                $availabilities[] = $date->format(\DateTime::ATOM);
            }
        } else {
            $period = CarbonPeriod::create(
                $nextOpeningDate, '15 minutes', $nextClosingDate,
                CarbonPeriod::EXCLUDE_END_DATE
            );
            foreach ($period as $date) {
                $availabilities[] = $date->format(\DateTime::ATOM);
            }

            $nextOpeningDate = $this->getNextOpeningDate($nextClosingDate);
            $nextClosingDate = $this->getNextClosingDate($nextOpeningDate);

            $period = CarbonPeriod::create(
                $nextOpeningDate, '15 minutes', $nextClosingDate,
                CarbonPeriod::EXCLUDE_END_DATE
            );
            foreach ($period as $date) {
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

    public function getActiveMenuTaxon()
    {
        return $this->activeMenuTaxon;
    }

    public function getMenuTaxon()
    {
        return $this->activeMenuTaxon;
    }

    public function setMenuTaxon(TaxonInterface $taxon)
    {
        $this->activeMenuTaxon = $taxon;
    }

    public function hasMenu()
    {
        return null !== $this->activeMenuTaxon;
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

    public function getFlatDeliveryPrice()
    {
        if ($this->contract) {
            return $this->contract->getFlatDeliveryPrice();
        }
    }

    public function getMinimumCartAmount()
    {
        if ($this->contract) {
            return $this->contract->getMinimumCartAmount();
        }
    }

    public function getOwners()
    {
        return $this->owners;
    }

    public function getProducts()
    {
        return $this->products;
    }

    public function hasProduct(ProductInterface $product)
    {
        return $this->products->contains($product);
    }

    public function addProduct(ProductInterface $product)
    {
        $product->setRestaurant($this);

        if (!$this->products->contains($product)) {
            $this->products->add($product);
        }
    }

    public function getProductOptions()
    {
        return $this->productOptions;
    }

    public function addProductOption(ProductOptionInterface $productOption)
    {
        if (!$this->productOptions->contains($productOption)) {
            $this->productOptions->add($productOption);
        }
    }

    public function getTaxons()
    {
        return $this->taxons;
    }

    public function addTaxon(TaxonInterface $taxon)
    {
        // TODO Check if this is a root taxon
        $this->taxons->add($taxon);
    }

    public function removeTaxon(TaxonInterface $taxon)
    {
        if ($this->getTaxons()->contains($taxon)) {
            $this->getTaxons()->removeElement($taxon);
        }
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

    public function getOrders()
    {
        return $this->orders;
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
}
