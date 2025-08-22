<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Action\Cart\DeleteItem as DeleteCartItem;
use AppBundle\Action\Cart\UpdateItem as UpdateCartItem;
use AppBundle\Action\MyOrders;
use AppBundle\Action\Order\Accept as OrderAccept;
use AppBundle\Action\Order\AddPlayer;
use AppBundle\Action\Order\Assign as OrderAssign;
use AppBundle\Action\Order\Cancel as OrderCancel;
use AppBundle\Action\Order\StartPreparing as OrderStartPreparing;
use AppBundle\Action\Order\FinishPreparing as OrderFinishPreparing;
use AppBundle\Action\Order\Centrifugo as CentrifugoController;
use AppBundle\Action\Order\CloneStripePayment;
use AppBundle\Action\Order\CreateInvitation as CreateInvitationController;
use AppBundle\Action\Order\CreateSetupIntentOrAttachPM;
use AppBundle\Action\Order\Delay as OrderDelay;
use AppBundle\Action\Order\Fulfill as OrderFulfill;
use AppBundle\Action\Order\GenerateInvoice as GenerateInvoiceController;
use AppBundle\Action\Order\Invoice as InvoiceController;
use AppBundle\Action\Order\LoopeatFormats as LoopeatFormatsController;
use AppBundle\Action\Order\MercadopagoPreference;
use AppBundle\Action\Order\Pay as OrderPay;
use AppBundle\Action\Order\PaymentDetails as PaymentDetailsController;
use AppBundle\Action\Order\PaymentMethods as PaymentMethodsController;
use AppBundle\Action\Order\Refuse as OrderRefuse;
use AppBundle\Action\Order\Restore as OrderRestore;
use AppBundle\Action\Order\Tip as OrderTip;
use AppBundle\Action\Order\Timing as OrderTiming;
use AppBundle\Api\Dto\CartItemInput;
use AppBundle\Api\Dto\ConfigurePaymentInput;
use AppBundle\Api\Dto\ConfigurePaymentOutput;
use AppBundle\Api\Dto\InvoiceLineItemGroupedByOrganization;
use AppBundle\Api\Dto\PaymentMethodsOutput;
use AppBundle\Api\Dto\StripePaymentMethodOutput;
use AppBundle\Api\Dto\LoopeatFormats;
use AppBundle\Api\Dto\LoopeatReturns;
use AppBundle\Api\Dto\EdenredCredentialsInput;
use AppBundle\Api\Filter\OrderDateFilter;
use AppBundle\Api\Filter\OrderStoreFilter;
use AppBundle\Api\State\CartItemProcessor;
use AppBundle\Api\State\ConfigurePaymentProcessor;
use AppBundle\Api\State\EdenredCredentialsProcessor;
use AppBundle\Api\State\InvoiceLineItemsGroupedByOrganizationProvider;
use AppBundle\Api\State\InvoiceLineItemsProvider;
use AppBundle\Api\State\LoopeatFormatsProcessor;
use AppBundle\Api\State\LoopeatReturnsProcessor;
use AppBundle\Api\State\ValidateOrderProvider;
use AppBundle\DataType\TsRange;
use AppBundle\Entity\Address;
use AppBundle\Entity\BusinessAccount;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LoopEat\OrderCredentials;
use AppBundle\Entity\ReusablePackaging;
use AppBundle\Entity\Task\RecurrenceRule;
use AppBundle\Entity\Vendor;
use AppBundle\Payment\MercadopagoPreferenceResponse;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Validator\Constraints\DabbaOrder as AssertDabbaOrder;
use AppBundle\Validator\Constraints\IsOrderModifiable as AssertOrderIsModifiable;
use AppBundle\Validator\Constraints\LoopEatOrder as AssertLoopEatOrder;
use AppBundle\Validator\Constraints\Order as AssertOrder;
use AppBundle\Validator\Constraints\ShippingAddress as AssertShippingAddress;
use AppBundle\Validator\Constraints\ShippingTimeRange as AssertShippingTimeRange;
use AppBundle\Vytal\CodeAwareTrait as VytalCodeAwareTrait;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Customer\Model\CustomerInterface;
use Sylius\Component\Order\Model\AdjustmentInterface as BaseAdjustmentInterface;
use Sylius\Component\Order\Model\Order as BaseOrder;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Promotion\Model\PromotionCouponInterface;
use Sylius\Component\Promotion\Model\PromotionInterface;
use Sylius\Component\Taxation\Model\TaxRateInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Webmozart\Assert\Assert as WMAssert;

/**
 * @see http://schema.org/Order Documentation on Schema.org
 */
#[ApiResource(
    types: ['http://schema.org/Order'],
    operations: [
        new Get(security: 'is_granted(\'view\', object)'),
        new Get(
            uriTemplate: '/orders/{id}/payment',
            controller: PaymentDetailsController::class,
            openapiContext: ['summary' => 'Get payment details for a Order resource.'],
            normalizationContext: ['api_sub_level' => true, 'groups' => ['payment_details']],
            security: 'is_granted(\'edit\', object)'
        ),
        new Get(
            uriTemplate: '/orders/{id}/payment_methods',
            types: ['PaymentMethodsOutput'],
            controller: PaymentMethodsController::class,
            openapiContext: ['summary' => 'Get available payment methods for a Order resource.'],
            normalizationContext: ['api_sub_level' => true],
            security: 'is_granted(\'edit\', object)',
            output: PaymentMethodsOutput::class
        ),
        new Put(
            uriTemplate: '/orders/{id}/pay',
            controller: OrderPay::class,
            openapiContext: ['summary' => 'Pays a Order resource.'],
            security: 'is_granted(\'edit\', object)'
        ),
        new Put(
            uriTemplate: '/orders/{id}/accept',
            controller: OrderAccept::class,
            openapiContext: ['summary' => 'Accepts a Order resource.'],
            security: 'is_granted(\'accept\', object)',
            deserialize: false
        ),
        new Put(
            uriTemplate: '/orders/{id}/refuse',
            controller: OrderRefuse::class,
            openapiContext: ['summary' => 'Refuses a Order resource.'],
            security: 'is_granted(\'refuse\', object)'
        ),
        new Put(
            uriTemplate: '/orders/{id}/delay',
            controller: OrderDelay::class,
            openapiContext: ['summary' => 'Delays a Order resource.'],
            security: 'is_granted(\'delay\', object)'
        ),
        new Put(
            uriTemplate: '/orders/{id}/fulfill',
            controller: OrderFulfill::class,
            openapiContext: ['summary' => 'Fulfills a Order resource.'],
            security: 'is_granted(\'fulfill\', object)'
        ),
        new Put(
            uriTemplate: '/orders/{id}/cancel',
            controller: OrderCancel::class,
            openapiContext: ['summary' => 'Cancels a Order resource.'],
            security: 'is_granted(\'cancel\', object)'
        ),
        new Put(
            uriTemplate: '/orders/{id}/start_preparing',
            controller: OrderStartPreparing::class,
            openapiContext: ['summary' => 'Starts preparing an Order resource.'],
            security: 'is_granted(\'start_preparing\', object)'
        ),
        new Put(
            uriTemplate: '/orders/{id}/finish_preparing',
            controller: OrderFinishPreparing::class,
            security: 'is_granted(\'finish_preparing\', object)',
            openapiContext: ['summary' => 'Finishes preparing an Order resource.']
        ),
        new Put(
            uriTemplate: '/orders/{id}/restore',
            controller: OrderRestore::class,
            openapiContext: ['summary' => 'Restores a cancelled Order resource.'],
            security: 'is_granted(\'restore\', object)'
        ),
        new Put(
            uriTemplate: '/orders/{id}/assign',
            controller: OrderAssign::class,
            openapiContext: ['summary' => 'Assigns a Order resource to a User.'],
            normalizationContext: ['groups' => ['cart']],
            validationContext: ['groups' => ['cart']]
        ),
        new Put(
            uriTemplate: '/orders/{id}/tip',
            controller: OrderTip::class,
            openapiContext: ['summary' => 'Updates tip amount of an Order resource.'],
            normalizationContext: ['groups' => ['cart']],
            security: 'is_granted(\'edit\', object)',
            validationContext: ['groups' => ['cart']]
        ),
        new Get(
            uriTemplate: '/orders/{id}/timing',
            controller: OrderTiming::class,
            openapiContext: [
                'summary' => 'Retrieves timing information about a Order resource.',
                'responses' => [
                    [
                        'description' => 'Order timing information',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'preparation' => ['type' => 'string'],
                                        'shipping' => ['type' => 'string'],
                                        'asap' => ['type' => 'string', 'format' => 'date-time'],
                                        'today' => ['type' => 'boolean'],
                                        'fast' => ['type' => 'boolean'],
                                        'diff' => ['type' => 'string'],
                                        'choices' => [
                                            'type' => 'array',
                                            'item' => ['type' => 'string', 'format' => 'date-time']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            security: 'is_granted(\'view\', object)'
        ),
        new Get(
            uriTemplate: '/orders/{id}/validate',
            normalizationContext: ['groups' => ['cart']],
            security: 'is_granted(\'edit\', object)',
            provider: ValidateOrderProvider::class
        ),
        new Put(
            uriTemplate: '/orders/{id}',
            normalizationContext: ['groups' => ['cart']],
            denormalizationContext: ['groups' => ['order_update']],
            security: 'is_granted(\'edit\', object)',
            validationContext: ['groups' => ['cart']]
        ),
        new Put(
            uriTemplate: '/orders/{id}/items/{itemId}',
            controller: UpdateCartItem::class,
            normalizationContext: ['groups' => ['cart']],
            denormalizationContext: ['groups' => ['cart']],
            security: 'is_granted(\'edit\', object)',
            validationContext: ['groups' => ['cart']]
        ),
        new Delete(
            uriTemplate: '/orders/{id}/items/{itemId}',
            status: 200,
            controller: DeleteCartItem::class,
            openapiContext: ['summary' => 'Deletes items from a Order resource.'],
            normalizationContext: ['groups' => ['cart']],
            // Disable WriteListener to avoid having empty 204 response
            security: 'is_granted(\'edit\', object)',
            validationContext: ['groups' => ['cart']],
            validate: false,
            write: false
        ),
        new Get(
            uriTemplate: '/orders/{id}/centrifugo',
            controller: CentrifugoController::class,
            openapiContext: ['summary' => 'Get Centrifugo connection details for a Order resource.'],
            normalizationContext: ['groups' => ['centrifugo', 'centrifugo_for_order']],
            security: 'is_granted(\'view\', object)'
        ),
        new Get(
            uriTemplate: '/orders/{id}/mercadopago-preference',
            controller: MercadopagoPreference::class,
            openapiContext: ['summary' => 'Creates a MercadoPago preference and returns its ID.'],
            security: 'is_granted(\'edit\', object)',
            output: MercadopagoPreferenceResponse::class
        ),
        new Get(
            uriTemplate: '/orders/{id}/invoice',
            controller: InvoiceController::class,
            openapiContext: ['summary' => 'Get Invoice for a Order resource.'],
            security: 'is_granted(\'view\', object)'
        ),
        new Post(
            uriTemplate: '/orders/{id}/invoice',
            controller: GenerateInvoiceController::class,
            openapiContext: ['summary' => 'Generate Invoice for a Order resource.'],
            normalizationContext: ['groups' => ['order']],
            security: 'is_granted(\'view\', object)'
        ),
        new Get(
            uriTemplate: '/orders/{id}/stripe/clone-payment-method/{paymentMethodId}',
            types: ['StripePaymentMethodOutput'],
            uriVariables: ['id'],
            controller: CloneStripePayment::class,
            security: 'is_granted(\'edit\', object)',
            output: StripePaymentMethodOutput::class
        ),
        new Post(
            uriTemplate: '/orders/{id}/stripe/create-setup-intent-or-attach-pm',
            controller: CreateSetupIntentOrAttachPM::class,
            security: 'is_granted(\'edit\', object)'
        ),
        new Post(
            uriTemplate: '/orders/{id}/create_invitation',
            status: 200,
            controller: CreateInvitationController::class,
            openapiContext: ['summary' => 'Generates an invitation link for an order'],
            normalizationContext: ['groups' => ['cart']],
            security: 'is_granted(\'edit\', object)',
            validate: false
        ),
        new Post(uriTemplate: '/orders/{id}/players', controller: AddPlayer::class),
        new Get(
            uriTemplate: '/orders/{id}/loopeat_formats',
            controller: LoopeatFormatsController::class,
            openapiContext: ['summary' => 'Get Loopeat formats for an order'],
            normalizationContext: ['api_sub_level' => true],
            security: 'is_granted(\'view\', object)',
            output: LoopeatFormats::class
        ),
        new Put(
            uriTemplate: '/orders/{id}/loopeat_formats',
            openapiContext: ['summary' => 'Update Loopeat formats for an order'],
            normalizationContext: ['groups' => ['cart', 'order']],
            denormalizationContext: ['groups' => ['update_loopeat_formats']],
            security: 'is_granted(\'view\', object)',
            input: LoopeatFormats::class,
            validate: false,
            processor: LoopeatFormatsProcessor::class
        ),
        new Post(
            uriTemplate: '/orders/{id}/loopeat_returns',
            openapiContext: ['summary' => 'Update Loopeat returns for an order'],
            normalizationContext: ['groups' => ['cart']],
            denormalizationContext: ['groups' => ['update_loopeat_returns']],
            security: 'is_granted(\'edit\', object)',
            input: LoopeatReturns::class,
            validate: false,
            processor: LoopeatReturnsProcessor::class
        ),
        new Put(
            uriTemplate: '/orders/{id}/edenred_credentials',
            openapiContext: ['summary' => 'Update Edenred credentials for an order'],
            normalizationContext: ['groups' => ['cart']],
            denormalizationContext: ['groups' => ['update_edenred_credentials']],
            security: 'is_granted(\'edit\', object)',
            input: EdenredCredentialsInput::class,
            validate: false,
            processor: EdenredCredentialsProcessor::class
        ),
        new Put(
            uriTemplate: '/orders/{id}/payment',
            openapiContext: ['summary' => 'Configure payment for a Order resource.'],
            normalizationContext: [
                'api_sub_level' => true,
                'groups' => ['order_configure_payment']
            ],
            denormalizationContext: ['groups' => ['order_configure_payment']],
            security: 'is_granted(\'edit\', object)',
            input: ConfigurePaymentInput::class,
            output: ConfigurePaymentOutput::class,
            validate: false,
            processor: ConfigurePaymentProcessor::class
        ),
        new GetCollection(security: 'is_granted(\'ROLE_ADMIN\')'),
        new Post(
            denormalizationContext: ['groups' => ['order_create', 'address_create']]
        ),
        new Post(
            uriTemplate: '/orders/timing',
            status: 200,
            controller: OrderTiming::class,
            openapiContext: [
                'summary' => 'Retrieves timing information about a Order resource.',
                'responses' => [
                    [
                        'description' => 'Order timing information',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'preparation' => ['type' => 'string'],
                                        'shipping' => ['type' => 'string'],
                                        'asap' => ['type' => 'string', 'format' => 'date-time'],
                                        'today' => ['type' => 'boolean'],
                                        'fast' => ['type' => 'boolean'],
                                        'diff' => ['type' => 'string'],
                                        'choices' => [
                                            'type' => 'array',
                                            'item' => ['type' => 'string', 'format' => 'date-time']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            normalizationContext: ['groups' => ['cart_timing']],
            denormalizationContext: ['groups' => ['order_create', 'address_create']],
            write: false
        ),
        new GetCollection(uriTemplate: '/me/orders', controller: MyOrders::class),
        new GetCollection(
            // FIXME Maybe it shouldn't be a path param
            // It should be like /invoice_line_items?grouped_by_organization=1
            uriTemplate: '/invoice_line_items/grouped_by_organization',
            openapiContext: [
                'summary' => 'Invoicing: Get the number of orders and sum total grouped by organization',
                'description' => 'Retrieves the collection of organizations with the number of orders and sum total for the specified filter, for example: ?state[]=new&state[]=accepted&state[]=fulfilled&date[after]=2025-02-01&date[before]=2025-02-28'
            ],
            normalizationContext: ['groups' => ['default_invoice_line_item']],
            security: 'is_granted(\'ROLE_ADMIN\')',
            provider: InvoiceLineItemsGroupedByOrganizationProvider::class
        ),
        new GetCollection(
            uriTemplate: '/invoice_line_items',
            openapiContext: [
                'summary' => 'Invoicing: Get the collection of orders',
                'description' => 'Retrieves the collection of Order resources for the given organizations and the specified filter'
            ],
            normalizationContext: ['groups' => ['default_invoice_line_item']],
            security: 'is_granted(\'ROLE_ADMIN\')',
            provider: InvoiceLineItemsProvider::class
        ),
        new GetCollection(
            uriTemplate: '/invoice_line_items/export',
            openapiContext: ['summary' => 'Invoicing: Get the collection of orders for export in the default format'],
            paginationEnabled: false,
            normalizationContext: ['groups' => ['export_invoice_line_item']],
            security: 'is_granted(\'ROLE_ADMIN\')',
            provider: InvoiceLineItemsProvider::class
        ),
        new GetCollection(
            uriTemplate: '/invoice_line_items/export/odoo',
            openapiContext: ['summary' => 'Invoicing: Get the collection of orders for export in the Odoo format'],
            paginationEnabled: false,
            normalizationContext: ['groups' => ['odoo_export_invoice_line_item']],
            security: 'is_granted(\'ROLE_ADMIN\')',
            provider: InvoiceLineItemsProvider::class
        )
    ],
    normalizationContext: ['groups' => ['order', 'address']],
    denormalizationContext: ['groups' => ['order_create']]
)]
#[AssertOrder(groups: ['Default'])]
#[AssertOrderIsModifiable(groups: ['cart'])]
#[AssertLoopEatOrder(groups: ['loopeat'])]
#[AssertDabbaOrder(groups: ['dabba'])]
#[ApiFilter(filterClass: OrderDateFilter::class, properties: ['date' => 'exact'])]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['state' => 'exact'])]
#[ApiFilter(filterClass: ExistsFilter::class, properties: ['exports'])]
#[ApiFilter(filterClass: OrderStoreFilter::class)]
class Order extends BaseOrder implements OrderInterface
{
    use VytalCodeAwareTrait;

    const STORE_TYPE_FOODTECH = 'FOODTECH';
    const STORE_TYPE_LASTMILE = 'LASTMILE';
    const STORE_TYPE = [
        self::STORE_TYPE_FOODTECH,
        self::STORE_TYPE_LASTMILE
    ];

    protected $customer;

    #[Assert\Valid]
    #[AssertShippingAddress]
    protected $shippingAddress;

    protected $billingAddress;

    protected $payments;

    protected $delivery;

    protected $events;

    protected $timeline;

    protected $channel;

    protected $promotionCoupon;

    protected $promotions;

    protected $reusablePackagingEnabled = false;

    protected $reusablePackagingPledgeReturn = 0;

    protected $receipt;

    /**
     * @var int|null
     */
    protected $tipAmount;

    #[AssertShippingTimeRange(groups: ['Default', 'ShippingTime'])]
    protected $shippingTimeRange;


    protected $nonprofit;


    #[Assert\Expression("!this.isTakeaway() or (this.isTakeaway() and this.getVendor().isFulfillmentMethodEnabled('collection'))", message: 'order.collection.not_available', groups: ['cart'])]
    protected $takeaway = false;

    protected $vendors;

    protected $invitation;

    protected $loopeatDetails;

    protected ?OrderCredentials $loopeatCredentials = null;

    protected $businessAccount;

    protected Collection $bookmarks;

    protected ?RecurrenceRule $subscription = null;

    protected Collection $exports;

    const SWAGGER_CONTEXT_TIMING_RESPONSE_SCHEMA = [
        "type" => "object",
        "properties" => [
            "preparation" => ['type' => 'string'],
            "shipping" => ['type' => 'string'],
            "asap" => ['type' => 'string', 'format' => 'date-time'],
            "today" => ['type' => 'boolean'],
            "fast" => ['type' => 'boolean'],
            "diff" => ['type' => 'string'],
            "choices" => ['type' => 'array', 'item' => ['type' => 'string', 'format' => 'date-time']],
        ]
    ];

    public function __construct()
    {
        parent::__construct();

        $this->payments = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->promotions = new ArrayCollection();
        $this->vendors = new ArrayCollection();
        $this->bookmarks = new ArrayCollection();
        $this->exports = new ArrayCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomer(?CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }

    public function getItemsSorted(): Collection
    {
        // Make sure items are always in the same order
        // We order them by id asc

        $itemsArray = $this->items->toArray();
        usort($itemsArray, function (OrderItemInterface $a, OrderItemInterface $b) {
            return $a->getId() <=> $b->getId();
        });
        return new ArrayCollection($itemsArray);
    }

    public function getTaxTotal(): int
    {
        $taxTotal = 0;

        foreach ($this->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $taxAdjustment) {
            $taxTotal += $taxAdjustment->getAmount();
        }
        foreach ($this->items as $item) {
            $taxTotal += $item->getTaxTotal();
        }

        return $taxTotal;
    }

    public function getItemsTaxTotal(): int
    {
        $taxTotal = 0;

        foreach ($this->items as $item) {
            $taxTotal += $item->getTaxTotal();
        }

        return $taxTotal;
    }

    public function getItemsTotalExcludingTax(): int
    {
        return $this->getItemsTotal() - $this->getItemsTaxTotal();
    }

    public function getItemsTaxTotalByRate($taxRate): int
    {
        if ($taxRate instanceof TaxRateInterface) {
            $taxRateCode = $taxRate->getCode();
        } else {
            $taxRateCode = $taxRate;
        }

        $taxTotal = 0;

        foreach ($this->items as $item) {
            foreach ($item->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $taxAdjustment) {
                if ($taxAdjustment->getOriginCode() === $taxRateCode) {
                    $taxTotal += $taxAdjustment->getAmount();
                }
            }
        }

        return $taxTotal;
    }

    public function getTaxTotalByRate($taxRate): int
    {
        if ($taxRate instanceof TaxRateInterface) {
            $taxRateCode = $taxRate->getCode();
        } else {
            $taxRateCode = $taxRate;
        }

        $taxTotal = 0;

        foreach ($this->items as $item) {
            foreach ($item->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $taxAdjustment) {
                if ($taxAdjustment->getOriginCode() === $taxRateCode) {
                    $taxTotal += $taxAdjustment->getAmount();
                }
            }
        }

        foreach ($this->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $taxAdjustment) {
            if ($taxAdjustment->getOriginCode() === $taxRateCode) {
                $taxTotal += $taxAdjustment->getAmount();
            }
        }

        return $taxTotal;
    }

    public function getFeeTotal(): int
    {
        $feeTotal = 0;

        foreach ($this->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT) as $feeAdjustment) {
            $feeTotal += $feeAdjustment->getAmount();
        }

        return $feeTotal;
    }

    public function getStripeFeeTotal(): int
    {
        $total = 0;
        foreach ($this->getAdjustments(AdjustmentInterface::STRIPE_FEE_ADJUSTMENT) as $adjustment) {
            $total += $adjustment->getAmount();
        }

        return $total;
    }

    public function getRevenue(): int
    {
        return $this->getTotal() - $this->getFeeTotal() - $this->getStripeFeeTotal();
    }

    public function getTransferAmount(LocalBusiness $restaurant): int
    {
        $vendor = $this->getVendorByRestaurant($restaurant);

        if ($vendor) {

            return $vendor->getTransferAmount();
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getRestaurant(): ?LocalBusiness
    {
        if (!$this->hasVendor() || $this->isMultiVendor()) {

            return null;
        }

        return $this->getRestaurants()->first();
    }

    #[SerializedName('restaurant')]
    public function setRestaurant(?LocalBusiness $restaurant): void
    {
        if (null !== $restaurant && $restaurant !== $this->getRestaurant()) {

            $this->vendors->clear();

            $this->clearItems();
            $this->setShippingTimeRange(null);

            $this->addRestaurant($restaurant);
        }
    }

    public function hasVendor(): bool
    {
        return count($this->getVendors()) > 0;
    }

    public function getStoreType(): ?string
    {
        if ($this->isMultiVendor()) {
            return null;
        }

        if (!is_null($this->getRestaurant())) {
            return self::STORE_TYPE_FOODTECH;
        }

        if (!is_null($this->getDelivery()?->getStore())) {
            return self::STORE_TYPE_LASTMILE;
        }

        throw new \LogicException('Cannot get store type for order without delivery or restaurant');
    }

    /**
     * {@inheritdoc}
     */
    public function getShippingAddress(): ?Address
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(?Address $shippingAddress): void
    {
        $this->shippingAddress = $shippingAddress;
    }

    /**
     * {@inheritdoc}
     */
    public function getBillingAddress(): ?Address
    {
        return $this->billingAddress;
    }

    public function setBillingAddress($billingAddress)
    {
        $this->billingAddress = $billingAddress;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @deprecated
     */
    #[SerializedName('shippedAt')]
    public function getShippedAt(): ?\DateTime
    {
        if (null !== $this->shippingTimeRange) {

            $lower = Carbon::make($this->shippingTimeRange->getLower());
            $upper = Carbon::make($this->shippingTimeRange->getUpper());

            return $lower->average($upper)->toDateTime();
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    /**
     * {@inheritdoc}
     */
    public function hasPayments(): bool
    {
        return !$this->payments->isEmpty();
    }

    /**
     * {@inheritdoc}
     */
    public function addPayment(PaymentInterface $payment): void
    {
        if (!$this->hasPayment($payment)) {
            $this->payments->add($payment);
            $payment->setOrder($this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removePayment(PaymentInterface $payment): void
    {
        if ($this->hasPayment($payment)) {
            $this->payments->removeElement($payment);
            $payment->setOrder(null);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasPayment(PaymentInterface $payment): bool
    {
        return $this->payments->contains($payment);
    }

    /**
     * {@inheritdoc}
     */
    public function getLastPayment(?string $state = null): ?PaymentInterface
    {
        if ($this->payments->isEmpty()) {
            return null;
        }

        // TODO Order payments by creation date

        $payment = $this->payments->filter(function (PaymentInterface $payment) use ($state): bool {
            return null === $state || $payment->getState() === $state;
        })->last();

        return $payment !== false ? $payment : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getDelivery(): ?Delivery
    {
        return $this->delivery;
    }

    /**
     * {@inheritdoc}
     */
    public function setDelivery(Delivery $delivery): void
    {
        $delivery->setOrder($this);

        $this->delivery = $delivery;
    }

    public function getTimeline(): ?OrderTimeline
    {
        return $this->timeline;
    }

    public function setTimeline(OrderTimeline $timeline): void
    {
        $timeline->setOrder($this);

        $this->timeline = $timeline;
    }

    public function getPreparationExpectedAt()
    {
        if (null !== $this->timeline) {
            $value = $this->timeline->getPreparationExpectedAt();

            // @see https://github.com/coopcycle/coopcycle-web/issues/2134
            return $value instanceof CarbonInterface ? $value->toDateTime() : $value;
        }
    }

    public function getPickupExpectedAt()
    {
        if (null !== $this->timeline) {
            $value = $this->timeline->getPickupExpectedAt();

            // @see https://github.com/coopcycle/coopcycle-web/issues/2134
            return $value instanceof CarbonInterface ? $value->toDateTime() : $value;
        }
    }

    public function getPreparationTime()
    {
        if (null !== $this->timeline) {
            return $this->timeline->getPreparationTime();
        }
    }

    public function getShippingTime()
    {
        if (null !== $this->timeline) {
            return $this->timeline->getShippingTime();
        }
    }

    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    public function setChannel(?ChannelInterface $channel): void
    {
        $this->channel = $channel;
    }

    /**
     * {@inheritdoc}
     */
    public function getPromotionCoupon(): ?PromotionCouponInterface
    {
        return $this->promotionCoupon;
    }

    /**
     * {@inheritdoc}
     */
    public function setPromotionCoupon(?PromotionCouponInterface $coupon): void
    {
        $this->promotionCoupon = $coupon;
    }

    /**
     * {@inheritdoc}
     */
    public function getPromotionSubjectTotal(): int
    {
        return $this->getItemsTotal();
    }

    /**
     * {@inheritdoc}
     */
    public function hasPromotion(PromotionInterface $promotion): bool
    {
        return $this->promotions->contains($promotion);
    }

    /**
     * {@inheritdoc}
     */
    public function addPromotion(PromotionInterface $promotion): void
    {
        if (!$this->hasPromotion($promotion)) {
            $this->promotions->add($promotion);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removePromotion(PromotionInterface $promotion): void
    {
        if ($this->hasPromotion($promotion)) {
            $this->promotions->removeElement($promotion);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPromotions(): Collection
    {
        return $this->promotions;
    }

    /**
     * {@inheritdoc}
     */
    public function containsDisabledProduct(): bool
    {
        foreach ($this->getItems() as $item) {
            if ($item instanceof OrderItemInterface && !$item->getVariant()->getProduct()->isEnabled()) {

                return true;
            }
        }

        return false;
    }

    public function isEligibleToReusablePackaging(): bool
    {
        $restaurant = $this->getRestaurant();

        if (null === $restaurant) {
            return false;
        }

        if (
            !$restaurant->isDepositRefundEnabled()
            && !$restaurant->isLoopeatEnabled()
            && !$restaurant->isVytalEnabled()
            && !$restaurant->isDabbaEnabled()
        ) {
            return false;
        }

        foreach ($this->getItems() as $item) {
            if (
                $item instanceof OrderItemInterface
                &&  $item->getVariant()->getProduct()->isReusablePackagingEnabled()
            ) {

                return true;
            }
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function isReusablePackagingEnabled()
    {
        return $this->reusablePackagingEnabled;
    }

    /**
     * @param mixed $reusablePackagingEnabled
     *
     * @return self
     */
    public function setReusablePackagingEnabled($reusablePackagingEnabled)
    {
        $this->reusablePackagingEnabled = $reusablePackagingEnabled;

        return $this;
    }

    public function setReusablePackagingPledgeReturn(int $reusablePackagingPledgeReturn)
    {
        $this->reusablePackagingPledgeReturn = $reusablePackagingPledgeReturn;
        return $this;
    }

    public function getReusablePackagingPledgeReturn()
    {
        return $this->reusablePackagingPledgeReturn;
    }

    public function getReusablePackagingQuantity(): int
    {
        $quantity = 0;
        foreach ($this->getItems() as $item) {

            $product = $item->getVariant()->getProduct();

            if ($product->isReusablePackagingEnabled()) {

                if (!$product->hasReusablePackagings()) {
                    continue;
                }

                foreach ($product->getReusablePackagings() as $reusablePackaging) {
                    $quantity += ceil($reusablePackaging->getUnits() * $item->getQuantity());
                }
            }
        }

        return $quantity;
    }

    public function getReusablePackagingAmount(): int
    {
        $amount = 0;
        foreach ($this->getItems() as $item) {

            $product = $item->getVariant()->getProduct();

            if ($product->isReusablePackagingEnabled()) {

                if (!$product->hasReusablePackagings()) {
                    continue;
                }

                foreach ($product->getReusablePackagings() as $reusablePackaging) {
                    $quantity = ceil($reusablePackaging->getUnits() * $item->getQuantity());
                    $amount += $reusablePackaging->getReusablePackaging()->getPrice() * $quantity;
                }
            }
        }

        return $amount;
    }

    public function getReceipt()
    {
        return $this->receipt;
    }

    public function getHasReceipt()
    {
        return $this->hasReceipt();
    }

    public function setReceipt($receipt)
    {
        $this->receipt = $receipt;
    }

    public function hasReceipt()
    {
        return null !== $this->receipt;
    }

    public function removeReceipt()
    {
        if ($this->hasReceipt()) {

            $receipt = $this->receipt;

            $this->receipt = null;

            return $receipt;
        }
    }

    public function getTipAmount()
    {
        return $this->tipAmount;
    }

    public function setTipAmount(int $tipAmount)
    {
        $this->tipAmount = max(0, $tipAmount);
    }

    public function getShippingTimeRange(): ?TsRange
    {
        return $this->shippingTimeRange;
    }

    public function setShippingTimeRange(?TsRange $shippingTimeRange)
    {
        $this->shippingTimeRange = $shippingTimeRange;
    }

    /**
     * {@inheritdoc}
     */
    public function isTakeaway(): bool
    {
        // HOTFIX
        // Shit can happen when:
        // - only "collection" fulfillment method is available
        // - the order was saved in session with "delivery" fulfillment method

        if ($this->getState() === self::STATE_CART) {
            $restaurant = $this->getRestaurant();
            if (null !== $restaurant) {
                if (!$restaurant->isFulfillmentMethodEnabled('delivery') && $restaurant->isFulfillmentMethodEnabled('collection')) {
                    $this->setTakeaway(true);
                }
            }
        }

        return $this->takeaway;
    }

    public function setTakeaway(bool $takeaway): void
    {
        $this->takeaway = $takeaway;

        if ($takeaway) {
            $this->setShippingAddress(null);
        }
    }

    #[SerializedName('fulfillmentMethod')]
    public function getFulfillmentMethod(): string
    {
        return $this->isTakeaway() ? 'collection' : 'delivery';
    }

    #[SerializedName('paymentMethod')]
    public function getPaymentMethod(): string
    {
        $payment = $this->getLastPayment();

        if ($payment && $payment->getMethod()) {
            return $payment->getMethod()->getCode();
        }

        return '';
    }

    #[SerializedName('fulfillmentMethod')]
    public function setFulfillmentMethod(string $fulfillmentMethod)
    {
        $this->setTakeaway($fulfillmentMethod === 'collection');
    }

    public function getRefundTotal(): int
    {
        $refundTotal = 0;

        foreach ($this->getPayments() as $payment) {
            if (PaymentInterface::STATE_COMPLETED === $payment->getState()) {
                $refundTotal += $payment->getRefundTotal();
            }
        }

        return $refundTotal;
    }

    public function hasRefunds(): bool
    {
        foreach ($this->getPayments() as $payment) {
            if (PaymentInterface::STATE_COMPLETED === $payment->getState()) {
                if ($payment->hasRefunds()) {

                    return true;
                }
            }
        }

        return false;
    }

    public function getRefunds(): array
    {
        $refunds = [];
        foreach ($this->getPayments() as $payment) {
            if (PaymentInterface::STATE_COMPLETED === $payment->getState()) {
                if ($payment->hasRefunds()) {
                    foreach ($payment->getRefunds() as $refund) {
                        $refunds[] = $refund;
                    }
                }
            }
        }

        return $refunds;
    }

    #[SerializedName('assignedTo')]
    #[Groups(['order', 'order_minimal'])]
    public function getAssignedTo()
    {
        if (null !== $this->getDelivery()) {
            $pickup = $this->getDelivery()->getPickup();

            if (null !== $pickup && $pickup->isAssigned()) {
                return $pickup->getAssignedCourier()->getUsername();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getUser(): ?UserInterface
    {
        if (null === $this->customer) {
            return null;
        }

        if ($this->customer instanceof UserInterface) {
            return $this->customer;
        }

        return $this->customer->getUser();
    }

    public function getVendorConditions(): ?Vendor
    {
        if (null !== $this->getBusinessAccount()) {
            return $this->getBusinessAccount()->getBusinessRestaurantGroup();
        }

        return $this->getVendor();
    }

    public function getVendor(): ?Vendor
    {
        if (!$this->hasVendor()) {

            return null;
        }

        $first = $this->getRestaurants()->first();

        return $this->isMultiVendor() ? $first->getHub() : $first;
    }

    public function getItemsGroupedByVendor(): \SplObjectStorage
    {
        $hash = new \SplObjectStorage();

        foreach ($this->getItems() as $item) {

            $product = $item->getVariant()->getProduct();
            $restaurant = $product->getRestaurant();

            if (null !== $restaurant) {
                $items = isset($hash[$restaurant]) ? $hash[$restaurant] : [];
                $hash[$restaurant] = array_merge($items, [$item]);
            }
        }

        return $hash;
    }

    #[SerializedName('adjustments')]
    #[Groups(['order', 'cart'])]
    public function getAdjustmentsHash(): array
    {
        $serializeAdjustment = function (BaseAdjustmentInterface $adjustment) {

            return [
                'id' => $adjustment->getId(),
                'label' => $adjustment->getLabel(),
                'amount' => $adjustment->getAmount(),
            ];
        };

        $deliveryAdjustments =
            array_map($serializeAdjustment, $this->getAdjustments(AdjustmentInterface::DELIVERY_ADJUSTMENT)->toArray());
        $deliveryPromotionAdjustments =
            array_map($serializeAdjustment, $this->getAdjustments(AdjustmentInterface::DELIVERY_PROMOTION_ADJUSTMENT)->toArray());
        $orderPromotionAdjustments =
            array_map($serializeAdjustment, $this->getAdjustments(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT)->toArray());
        $reusablePackagingAdjustments =
            array_map($serializeAdjustment, $this->getAdjustments(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT)->toArray());
        $taxAdjustments =
            array_map($serializeAdjustment, $this->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)->toArray());
        $tipAdjustments =
            array_map($serializeAdjustment, $this->getAdjustments(AdjustmentInterface::TIP_ADJUSTMENT)->toArray());
        $incidentAdjustments =
            array_map($serializeAdjustment, $this->getAdjustments(AdjustmentInterface::INCIDENT_ADJUSTMENT)->toArray());

        return [
            AdjustmentInterface::DELIVERY_ADJUSTMENT => array_values($deliveryAdjustments),
            AdjustmentInterface::DELIVERY_PROMOTION_ADJUSTMENT => array_values($deliveryPromotionAdjustments),
            AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT => array_values($orderPromotionAdjustments),
            AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT => array_values($reusablePackagingAdjustments),
            AdjustmentInterface::TAX_ADJUSTMENT => array_values($taxAdjustments),
            AdjustmentInterface::TIP_ADJUSTMENT => array_values($tipAdjustments),
            AdjustmentInterface::INCIDENT_ADJUSTMENT => array_values($incidentAdjustments),
        ];
    }

    public function getItemsTotalForRestaurant(LocalBusiness $restaurant): int
    {
        $total = 0;
        foreach ($this->getItems() as $item) {
            if ($restaurant->hasProduct($item->getVariant()->getProduct())) {
                $total += $item->getTotal();
            }
        }

        return $total;
    }

    public function getPercentageForRestaurant(LocalBusiness $restaurant): float
    {
        $total = $this->getItemsTotal();

        if (0 === $total) {
            return 0.0;
        }

        $itemsTotal = $this->getItemsTotalForRestaurant($restaurant);

        return round($itemsTotal / $total, 4);
    }

    public function getRestaurants(): Collection
    {
        return $this->vendors->map(fn(OrderVendor $vendor) => $vendor->getRestaurant());
    }

    public function getVendors(): Collection
    {
        return $this->vendors;
    }

    public function getVendorByRestaurant(LocalBusiness $restaurant): ?OrderVendor
    {
        foreach ($this->vendors as $vendor) {
            if ($vendor->getRestaurant() === $restaurant) {
                return $vendor;
            }
        }

        return null;
    }

    public function containsRestaurant(LocalBusiness $restaurant): bool
    {
        foreach ($this->vendors as $vendor) {
            if ($vendor->getRestaurant() === $restaurant) {
                return true;
            }
        }

        return false;
    }

    public function addRestaurant(LocalBusiness $restaurant, int $itemsTotal = 0, int $transferAmount = 0)
    {
        $vendor = $this->getVendorByRestaurant($restaurant);

        if (null === $vendor) {
            $vendor = new OrderVendor($this, $restaurant);
            $this->vendors->add($vendor);
        }

        $vendor->setItemsTotal($itemsTotal);
        $vendor->setTransferAmount($transferAmount);
    }

    public function isMultiVendor(): bool
    {
        return $this->hasVendor() && count($this->getVendors()) > 1;
    }

    public function getPickupAddress(): ?Address
    {
        if ($this->hasVendor()) {
            return $this->getVendor()->getAddress();
        }

        return null;
    }

    public function getNotificationRecipients(): array
    {
        $recipients = new ArrayCollection();

        foreach ($this->getRestaurants() as $restaurant) {
            foreach ($restaurant->getOwners() as $owner) {
                $recipients->add($owner);
            }
        }

        return array_unique($recipients->toArray());
    }

    public function supportsEdenred(): bool
    {
        if ($this->isMultiVendor() || !$this->hasVendor()) {

            return false;
        }

        return $this->getRestaurant()->supportsEdenred();
    }

    public function getAlcoholicItemsTotal(): int
    {
        $total = 0;

        foreach ($this->getItems() as $item) {

            WMAssert::isInstanceOf($item, OrderItemInterface::class);

            if ($item->getVariant()->getProduct()->isAlcohol()) {
                $total += $item->getTotal();
            }
        }

        return $total;
    }

    public function isLoopeat(): bool
    {
        if (!$this->hasVendor() || $this->isMultiVendor() || !$this->isReusablePackagingEnabled()) {

            return false;
        }

        return $this->getRestaurant()->isLoopeatEnabled();
    }

    public function supportsCashOnDelivery(): bool
    {
        if ($this->isMultiVendor() || !$this->hasVendor()) {

            return false;
        }

        return $this->getRestaurant()->isCashOnDeliveryEnabled();
    }

    public function isFree(): bool
    {
        return !$this->isEmpty() && $this->getItemsTotal() > 0 && $this->getTotal() === 0;
    }

    public function getNonprofit()
    {
        return $this->nonprofit;
    }

    public function setNonprofit($nonprofit)
    {
        $this->nonprofit = $nonprofit;
    }

    public function getInvitation()
    {
        return $this->invitation;
    }

    public function createInvitation()
    {
        $this->invitation = new OrderInvitation();
        $this->invitation->setOrder($this);
    }

    public function getRequiredAmountForLoopeat(): int
    {
        $amount = 0;
        foreach ($this->getItems() as $item) {

            $product = $item->getVariant()->getProduct();

            if ($product->isReusablePackagingEnabled()) {

                if (!$product->hasReusablePackagings()) {
                    continue;
                }

                foreach ($product->getReusablePackagings() as $reusablePackaging) {
                    $data = $reusablePackaging->getReusablePackaging()->getData();
                    $amount += (int) ceil($data['cost_cents'] * ($item->getQuantity() * $reusablePackaging->getUnits()));
                }
            }
        }

        return $amount;
    }

    public function getFormatsToDeliverForLoopeat(): array
    {
        $formats = [];
        foreach ($this->getItems() as $item) {

            $product = $item->getVariant()->getProduct();

            if ($product->isReusablePackagingEnabled()) {

                if (!$product->hasReusablePackagings()) {
                    continue;
                }

                foreach ($product->getReusablePackagings() as $reusablePackaging) {
                    $data = $reusablePackaging->getReusablePackaging()->getData();
                    $formats[] = [
                        'format_id' => $data['id'],
                        'quantity' => ($item->getQuantity() * $reusablePackaging->getUnits()),
                    ];
                }
            }
        }

        // Make sure same formats do not appear twice
        $formats = array_reduce($formats, function ($carry, $item) {
            foreach ($carry as $index => $el) {
                if ($el['format_id'] === $item['format_id']) {
                    $carry[$index]['quantity'] += $item['quantity'];

                    return $carry;
                }
            }

            $carry[] = $item;

            return $carry;
        }, []);

        return $formats;
    }

    private function getLoopeatDetails()
    {
        if (null === $this->loopeatDetails) {
            $this->loopeatDetails = new LoopeatOrderDetails();
            $this->loopeatDetails->setOrder($this);
        }

        return $this->loopeatDetails;
    }

    public function setLoopeatOrderId($loopeatOrderId)
    {
        $this->getLoopeatDetails()->setOrderId($loopeatOrderId);
    }

    public function getLoopeatOrderId()
    {
        return $this->getLoopeatDetails()->getOrderId();
    }

    public function setLoopeatReturns(array $returns = [])
    {
        $this->getLoopeatDetails()->setReturns($returns);
    }

    public function getLoopeatReturns()
    {
        return $this->getLoopeatDetails()->getReturns();
    }

    public function hasLoopeatReturns()
    {
        return $this->getLoopeatDetails()->hasReturns();
    }

    public function countLoopeatReturns()
    {
        return $this->getLoopeatDetails()->countReturns();
    }

    public function getReturnsAmountForLoopeat(): int
    {
        $reusablePackagings = $this->getRestaurant()->getReusablePackagings();

        $findFormat = function ($formatId) use ($reusablePackagings) {
            foreach ($reusablePackagings as $reusablePackaging) {
                $data = $reusablePackaging->getData();
                if ($data['id'] === $formatId) {
                    return $data;
                }
            }
        };

        $amount = 0;
        foreach ($this->getLoopeatReturns() as $return) {
            $format = $findFormat($return['format_id']);
            $amount += ($format['cost_cents'] * $return['quantity']);
        }

        return $amount;
    }

    public function setLoopeatDeliver(array $deliver = [])
    {
        $this->getLoopeatDetails()->setDeliver($deliver);
    }

    public function getLoopeatDeliver()
    {
        return $this->getLoopeatDetails()->getDeliver();
    }

    public function getLoopeatAccessToken()
    {
        $ownToken = $this->loopeatCredentials?->getLoopeatAccessToken();
        $customerToken = $this->customer?->getLoopeatAccessToken();

        return $customerToken ?? $ownToken;
    }

    public function setLoopeatAccessToken($accessToken)
    {
        if (null !== $this->customer) {
            $this->customer->setLoopeatAccessToken($accessToken);
        }

        if (null === $this->loopeatCredentials) {

            $this->loopeatCredentials = new OrderCredentials();
            $this->loopeatCredentials->setOrder($this);
        }

        $this->loopeatCredentials->setLoopeatAccessToken($accessToken);
    }

    public function getLoopeatRefreshToken()
    {
        $ownToken = $this->loopeatCredentials?->getLoopeatRefreshToken();
        $customerToken = $this->customer?->getLoopeatRefreshToken();

        return $customerToken ?? $ownToken;
    }

    public function setLoopeatRefreshToken($refreshToken)
    {
        if (null !== $this->customer) {
            $this->customer->setLoopeatRefreshToken($refreshToken);
        }

        if (null === $this->loopeatCredentials) {

            $this->loopeatCredentials = new OrderCredentials();
            $this->loopeatCredentials->setOrder($this);
        }

        $this->loopeatCredentials->setLoopeatRefreshToken($refreshToken);
    }

    public function hasLoopEatCredentials(): bool
    {
        return null !== $this->loopeatCredentials && $this->loopeatCredentials->hasLoopEatCredentials();
    }

    public function clearLoopEatCredentials()
    {
        if (null === $this->loopeatCredentials) {

            return;
        }

        $this->loopeatCredentials->setOrder(null);
        $this->loopeatCredentials = null;
    }

    public function getLoopeatReturnsCount(): int
    {
        $count = 0;

        foreach ($this->getLoopeatReturns() as $return) {
            $count += $return['quantity'];
        }

        return $count;
    }

    public function getLoopeatReturnsAsText(): string
    {
        $text = '';

        $reusablePackagings = $this->getRestaurant()->getReusablePackagings();

        $findFormat = function ($formatId) use ($reusablePackagings) {
            foreach ($reusablePackagings as $reusablePackaging) {
                $data = $reusablePackaging->getData();
                if ($data['id'] === $formatId) {
                    return $data;
                }
            }
        };

        foreach ($this->getLoopeatReturns() as $return) {
            $format = $findFormat($return['format_id']);
            $text .= "‒ {$format['title']} × {$return['quantity']}\n";
        }

        return $text;
    }

    public function supportsLoopeat(): bool
    {
        foreach ($this->getVendors() as $vendor) {
            if ($vendor->getRestaurant()->isLoopeatEnabled() && $vendor->getRestaurant()->hasLoopEatCredentials()) {

                return true;
            }
        }

        return false;
    }

    public function hasEvent(string $type): bool
    {
        foreach ($this->getEvents() as $event) {
            if ($event->getType() === $type) {
                return true;
            }
        }

        return false;
    }

    public function getBusinessAccount(): ?BusinessAccount
    {
        return $this->businessAccount;
    }

    public function setBusinessAccount(?BusinessAccount $businessAccount): void
    {
        $this->businessAccount = $businessAccount;
    }

    public function isBusiness(): bool
    {
        return null !== $this->businessAccount;
    }

    public function getPickupAddresses(): Collection
    {
        return $this->getRestaurants()->map(fn(LocalBusiness $restaurant): Address => $restaurant->getAddress());
    }

    /**
     * To get bookmarks that current user has access to use OrderManager::hasBookmark instead
     * @return Collection all bookmarks set by different users
     */
    public function getBookmarks(): Collection
    {
        return $this->bookmarks;
    }

    public function supportsPaygreen(): bool
    {
        if ($this->isMultiVendor() || !$this->hasVendor()) {

            return false;
        }

        return $this->getRestaurant()->supportsPaygreen() && 'paygreen' === $this->getRestaurant()->getPaymentGateway();
    }

    public function getSubscription(): ?RecurrenceRule
    {
        return $this->subscription;
    }

    public function setSubscription(?RecurrenceRule $subscription): void
    {
        $this->subscription = $subscription;
    }

    #[SerializedName('hasEdenredCredentials')]
    #[Groups(['order', 'order_update', 'cart'])]
    public function hasEdenredCredentials(): bool
    {
        /** @var \AppBundle\Sylius\Customer\CustomerInterface|null */
        $customer = $this->getCustomer();

        if (null === $customer) {
            return false;
        }

        return $customer->hasEdenredCredentials();
    }

    /**
     * {@inheritdoc}
     */
    public function getLastPaymentByMethod(string|array $method, ?string $state = null): ?PaymentInterface
    {
        if ($this->payments->isEmpty()) {
            return null;
        }

        $iterator = $this->payments->getIterator();
        $iterator->uasort(function ($a, $b) {
            return ($a->getCreatedAt() < $b->getCreatedAt()) ? -1 : 1;
        });
        $payments = new ArrayCollection(iterator_to_array($iterator));

        $payment = $payments->filter(function (PaymentInterface $payment) use ($method, $state): bool {
            $__filter = null;
            if (is_string($method)) {
                $__filter = fn(PaymentInterface $payment) => $payment->getMethod()->getCode() === $method;
            }
            if (is_array($method)) {
                $__filter = fn(PaymentInterface $payment) => in_array($payment->getMethod()->getCode(), $method);
            }
            return (null === $state || $payment->getState() === $state) && $__filter($payment);
        })->last();

        return $payment !== false ? $payment : null;
    }

    public function isZeroWaste(): bool
    {
        if (!$this->isReusablePackagingEnabled()) {
            return false;
        }

        foreach ($this->getItems() as $item) {

            $product = $item->getVariant()->getProduct();

            if ($product->isReusablePackagingEnabled()) {

                if ($product->hasReusablePackagings()) {

                    return true;
                }
            }
        }

        return false;
    }

    public function getLoopeatFormatById(int $formatId): ?array
    {
        $reusablePackagings = $this->getRestaurant()->getReusablePackagings();

        foreach ($reusablePackagings as $reusablePackaging) {
            if (ReusablePackaging::TYPE_LOOPEAT === $reusablePackaging->getType()) {
                $data = $reusablePackaging->getData();
                if ($data['id'] === $formatId) {
                    return $data;
                }
            }
        }

        return null;
    }

    public function isFoodtech(): bool
    {
        //FIXME: combine with $this->getStoreType() implementation
        return $this->hasVendor();
    }

    public function isDeliveryForStore(): bool
    {
        //FIXME: combine with $this->getStoreType() implementation

        // There are two types of On Demand Delivery orders:
        // 1. Local Commerce and Last Mile ("store") orders (this method)
        // 2. B2C via Order form
        return !is_null($this->getDelivery()?->getStore());
    }

    public function getDeliveryPrice(): PriceInterface
    {
        if ($this->isFoodtech()) {
            //FIXME: get the delivery price for food tech orders from Adjustments
            return new PricingRulesBasedPrice(0);
        }

        /** @var OrderItemInterface|false $deliveryItem */
        $deliveryItem = $this->getItems()->first();

        if (false === $deliveryItem) {
            throw new \LogicException('Order has no delivery price');
        }

        $productVariant = $deliveryItem->getVariant();

        if ($pricingRulesSet = $productVariant->getPricingRuleSet()) {
            // An order might contain multiple order items, that's why we need to return order total
            // we also assume here that all order items will have the same pricingRulesSet
            return new PricingRulesBasedPrice($this->getTotal(), $pricingRulesSet);
        } else {
            // Some productVariants created before $pricingRulesSet was introduced
            // may have a price calculated based on a pricing rule set, but pricingRulesSet is null

            // Normally there will be only one order item when using an arbitrary price
            // but we return order total just in case

            // custom price
            return new ArbitraryPrice($productVariant->getName(), $this->getTotal());
        }
    }

    public function getExports(): Collection
    {
        return $this->exports;
    }
}
