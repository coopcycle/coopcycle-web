<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Cart\AddItem as AddCartItem;
use AppBundle\Action\Cart\DeleteItem as DeleteCartItem;
use AppBundle\Action\Cart\UpdateItem as UpdateCartItem;
use AppBundle\Action\MyOrders;
use AppBundle\Action\Order\Accept as OrderAccept;
use AppBundle\Action\Order\AddPlayer as AddPlayer;
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
use AppBundle\Action\Order\UpdateLoopeatFormats as UpdateLoopeatFormatsController;
use AppBundle\Action\Order\UpdateLoopeatReturns as UpdateLoopeatReturnsController;
use AppBundle\Api\Dto\CartItemInput;
use AppBundle\Api\Dto\PaymentMethodsOutput;
use AppBundle\Api\Dto\StripePaymentMethodOutput;
use AppBundle\Api\Dto\LoopeatFormats as LoopeatFormatsOutput;
use AppBundle\Api\Dto\LoopeatReturns;
use AppBundle\DataType\TsRange;
use AppBundle\Entity\Address;
use AppBundle\Entity\BusinessAccount;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LoopEat\OrderCredentials;
use AppBundle\Entity\Vendor;
use AppBundle\Filter\OrderDateFilter;
use AppBundle\LoopEat\OAuthCredentialsInterface as LoopeatOAuthCredentialsInterface;
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
 *
 * @ApiResource(iri="http://schema.org/Order",
 *   collectionOperations={
 *     "get"={
 *       "method"="GET",
 *       "security"="is_granted('ROLE_ADMIN')"
 *     },
 *     "post"={
 *       "method"="POST",
 *       "denormalization_context"={"groups"={"order_create", "address_create"}}
 *     },
 *     "timing"={
 *       "method"="POST",
 *       "path"="/orders/timing",
 *       "write"=false,
 *       "status"=200,
 *       "denormalization_context"={"groups"={"order_create", "address_create"}},
 *       "normalization_context"={"groups"={"cart_timing"}},
 *       "openapi_context"={
 *         "summary"="Retrieves timing information about a Order resource.",
 *         "responses"={
 *           "200"={
 *             "description"="Order timing information",
 *             "content"={
 *               "application/json": {
 *                 "schema"=Order::SWAGGER_CONTEXT_TIMING_RESPONSE_SCHEMA
 *               }
 *             }
 *           }
 *         }
 *       }
 *     },
 *     "my_orders"={
 *       "method"="GET",
 *       "path"="/me/orders",
 *       "controller"=MyOrders::class
 *     }
 *   },
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "security"="is_granted('view', object)"
 *     },
 *     "payment_details"={
 *       "method"="GET",
 *       "path"="/orders/{id}/payment",
 *       "controller"=PaymentDetailsController::class,
 *       "security"="is_granted('edit', object)",
 *       "openapi_context"={
 *         "summary"="Get payment details for a Order resource."
 *       }
 *     },
 *     "payment_methods"={
 *       "method"="GET",
 *       "path"="/orders/{id}/payment_methods",
 *       "controller"=PaymentMethodsController::class,
 *       "output"=PaymentMethodsOutput::class,
 *       "normalization_context"={"api_sub_level"=true},
 *       "security"="is_granted('edit', object)",
 *       "openapi_context"={
 *         "summary"="Get available payment methods for a Order resource."
 *       }
 *     },
 *     "pay"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/pay",
 *       "controller"=OrderPay::class,
 *       "security"="is_granted('edit', object)",
 *       "openapi_context"={
 *         "summary"="Pays a Order resource."
 *       }
 *     },
 *     "accept"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/accept",
 *       "controller"=OrderAccept::class,
 *       "security"="is_granted('accept', object)",
 *       "deserialize"=false,
 *       "openapi_context"={
 *         "summary"="Accepts a Order resource."
 *       }
 *     },
 *     "refuse"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/refuse",
 *       "controller"=OrderRefuse::class,
 *       "security"="is_granted('refuse', object)",
 *       "openapi_context"={
 *         "summary"="Refuses a Order resource."
 *       }
 *     },
 *     "delay"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/delay",
 *       "controller"=OrderDelay::class,
 *       "security"="is_granted('delay', object)",
 *       "openapi_context"={
 *         "summary"="Delays a Order resource."
 *       }
 *     },
 *     "fulfill"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/fulfill",
 *       "controller"=OrderFulfill::class,
 *       "security"="is_granted('fulfill', object)",
 *       "openapi_context"={
 *         "summary"="Fulfills a Order resource."
 *       }
 *     },
 *     "cancel"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/cancel",
 *       "controller"=OrderCancel::class,
 *       "security"="is_granted('cancel', object)",
 *       "openapi_context"={
 *         "summary"="Cancels a Order resource."
 *       }
 *     },
 *     "start_preparing"={
 *        "method"="PUT",
 *        "path"="/orders/{id}/start_preparing",
 *        "controller"=OrderStartPreparing::class,
 *        "security"="is_granted('start_preparing', object)",
 *        "openapi_context"={
 *          "summary"="Starts preparing an Order resource."
 *        }
 *      },
 *     "finish_preparing"={
 *        "method"="PUT",
 *        "path"="/orders/{id}/finish_preparing",
 *        "controller"=OrderFinishPreparing::class,
 *        "security"="is_granted('finish_preparing', object)",
 *        "openapi_context"={
 *          "summary"="Finishes preparing an Order resource."
 *        }
 *      },
 *     "restore"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/restore",
 *       "controller"=OrderRestore::class,
 *       "security"="is_granted('restore', object)",
 *       "openapi_context"={
 *         "summary"="Restores a cancelled Order resource."
 *       }
 *     },
 *     "assign"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/assign",
 *       "controller"=OrderAssign::class,
 *       "validation_groups"={"cart"},
 *       "normalization_context"={"groups"={"cart"}},
 *       "openapi_context"={
 *         "summary"="Assigns a Order resource to a User."
 *       }
 *     },
 *     "tip"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/tip",
 *       "controller"=OrderTip::class,
 *       "validation_groups"={"cart"},
 *       "security"="is_granted('edit', object)",
 *       "normalization_context"={"groups"={"cart"}},
 *       "openapi_context"={
 *         "summary"="Updates tip amount of an Order resource."
 *       }
 *     },
 *     "get_cart_timing"={
 *       "method"="GET",
 *       "path"="/orders/{id}/timing",
 *       "security"="is_granted('view', object)",
 *       "openapi_context"={
 *         "summary"="Retrieves timing information about a Order resource.",
 *         "responses"={
 *           "200"={
 *             "description"="Order timing information",
 *             "content"={
 *               "application/json": {
 *                 "schema"=Order::SWAGGER_CONTEXT_TIMING_RESPONSE_SCHEMA
 *               }
 *             }
 *           }
 *         }
 *       }
 *     },
 *     "validate"={
 *       "method"="GET",
 *       "path"="/orders/{id}/validate",
 *       "normalization_context"={"groups"={"cart"}},
 *       "security"="is_granted('edit', object)"
 *     },
 *     "put_cart"={
 *       "method"="PUT",
 *       "path"="/orders/{id}",
 *       "validation_groups"={"cart"},
 *       "normalization_context"={"groups"={"cart"}},
 *       "denormalization_context"={"groups"={"order_update"}},
 *       "security"="is_granted('edit', object)"
 *     },
 *     "post_cart_items"={
 *       "method"="POST",
 *       "path"="/orders/{id}/items",
 *       "input"=CartItemInput::class,
 *       "controller"=AddCartItem::class,
 *       "validation_groups"={"cart"},
 *       "denormalization_context"={"groups"={"cart"}},
 *       "normalization_context"={"groups"={"cart"}},
 *       "security"="is_granted('edit', object)",
 *       "openapi_context"={
 *         "summary"="Adds items to a Order resource."
 *       }
 *     },
 *     "put_item"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/items/{itemId}",
 *       "controller"=UpdateCartItem::class,
 *       "validation_groups"={"cart"},
 *       "denormalization_context"={"groups"={"cart"}},
 *       "normalization_context"={"groups"={"cart"}},
 *       "security"="is_granted('edit', object)"
 *     },
 *     "delete_item"={
 *       "method"="DELETE",
 *       "path"="/orders/{id}/items/{itemId}",
 *       "controller"=DeleteCartItem::class,
 *       "validation_groups"={"cart"},
 *       "normalization_context"={"groups"={"cart"}},
 *       "validate"=false,
 *       "write"=false,
 *       "status"=200,
 *       "security"="is_granted('edit', object)",
 *       "openapi_context"={
 *         "summary"="Deletes items from a Order resource."
 *       }
 *     },
 *     "centrifugo"={
 *       "method"="GET",
 *       "path"="/orders/{id}/centrifugo",
 *       "controller"=CentrifugoController::class,
 *       "normalization_context"={"groups"={"centrifugo", "centrifugo_for_order"}},
 *       "security"="is_granted('view', object)",
 *       "openapi_context"={
 *         "summary"="Get Centrifugo connection details for a Order resource."
 *       }
 *     },
 *     "mercadopago_preference"={
 *       "method"="GET",
 *       "path"="/orders/{id}/mercadopago-preference",
 *       "controller"=MercadopagoPreference::class,
 *       "output"=MercadopagoPreferenceResponse::class,
 *       "security"="is_granted('edit', object)",
 *       "openapi_context"={
 *         "summary"="Creates a MercadoPago preference and returns its ID."
 *       }
 *     },
 *     "invoice"={
 *      "method"="GET",
 *      "path"="/orders/{id}/invoice",
 *      "controller"=InvoiceController::class,
 *      "security"="is_granted('view', object)",
 *      "openapi_context"={
 *        "summary"="Get Invoice for a Order resource."
 *      }
 *     },
 *     "generate_invoice"={
 *      "method"="POST",
 *      "path"="/orders/{id}/invoice",
 *      "normalization_context"={"groups"={"order"}},
 *      "controller"=GenerateInvoiceController::class,
 *      "security"="is_granted('view', object)",
 *      "openapi_context"={
 *        "summary"="Generate Invoice for a Order resource."
 *      }
 *     },
 *     "stripe_clone_payment_method"={
 *      "method"="GET",
 *      "path"="/orders/{id}/stripe/clone-payment-method/{paymentMethodId}",
 *      "controller"=CloneStripePayment::class,
 *      "output"=StripePaymentMethodOutput::class,
 *      "security"="is_granted('edit', object)",
 *      "openapi_context"={
 *        "summary"=""
 *      }
 *     },
 *     "stripe_create_setup_intent_or_attach_pm"={
 *      "method"="POST",
 *      "path"="/orders/{id}/stripe/create-setup-intent-or-attach-pm",
 *      "controller"=CreateSetupIntentOrAttachPM::class,
 *      "security"="is_granted('edit', object)",
 *      "openapi_context"={
 *        "summary"=""
 *      }
 *     },
 *     "create_invitation"={
 *      "method"="POST",
 *      "path"="/orders/{id}/create_invitation",
 *      "status"=200,
 *      "security"="is_granted('edit', object)",
 *      "normalization_context"={"groups"={"cart"}},
 *      "controller"=CreateInvitationController::class,
 *      "validate"=false,
 *      "openapi_context"={
 *       "summary"="Generates an invitation link for an order"
 *      }
 *     },
 *     "add_player"={
 *      "method"="POST",
 *      "path"="/orders/{id}/players",
 *      "controller"=AddPlayer::class,
 *     },
 *     "loopeat_formats"={
 *       "method"="GET",
 *       "path"="/orders/{id}/loopeat_formats",
 *       "controller"=LoopeatFormatsController::class,
 *       "output"=LoopeatFormatsOutput::class,
 *       "normalization_context"={"api_sub_level"=true},
 *       "security"="is_granted('view', object)",
 *       "openapi_context"={
 *         "summary"="Get Loopeat formats for an order"
 *       }
 *     },
 *     "update_loopeat_formats"={
 *       "method"="PUT",
 *       "path"="/orders/{id}/loopeat_formats",
 *       "controller"=UpdateLoopeatFormatsController::class,
 *       "security"="is_granted('view', object)",
 *       "input"=LoopeatFormatsOutput::class,
 *       "validate"=false,
 *       "normalization_context"={"groups"={"cart", "order"}},
 *       "denormalization_context"={"groups"={"update_loopeat_formats"}},
 *       "openapi_context"={
 *         "summary"="Update Loopeat formats for an order"
 *       }
 *     },
 *     "update_loopeat_returns"={
 *       "method"="POST",
 *       "path"="/orders/{id}/loopeat_returns",
 *       "controller"=UpdateLoopeatReturnsController::class,
 *       "security"="is_granted('edit', object)",
 *       "input"=LoopeatReturns::class,
 *       "validate"=false,
 *       "normalization_context"={"groups"={"cart"}},
 *       "denormalization_context"={"groups"={"update_loopeat_returns"}},
 *       "openapi_context"={
 *         "summary"="Update Loopeat returns for an order"
 *       }
 *     },
 *   },
 *   attributes={
 *     "denormalization_context"={"groups"={"order_create"}},
 *     "normalization_context"={"groups"={"order", "address"}}
 *   }
 * )
 * @ApiFilter(OrderDateFilter::class, properties={"date": "exact"})
 *
 * @AssertOrder(groups={"Default"})
 * @AssertOrderIsModifiable(groups={"cart"})
 * @AssertLoopEatOrder(groups={"loopeat"})
 * @AssertDabbaOrder(groups={"dabba"})
 */
class Order extends BaseOrder implements OrderInterface
{
    use VytalCodeAwareTrait;

    protected $customer;

    /**
     * @Assert\Valid
     * @AssertShippingAddress
     */
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
    protected $tipAmount = null;

    /**
     * @AssertShippingTimeRange(groups={"Default", "ShippingTime"})
     */
    protected $shippingTimeRange;


    protected $nonprofit;


    /**
     * @Assert\Expression(
     *   "!this.isTakeaway() or (this.isTakeaway() and this.getVendor().isFulfillmentMethodEnabled('collection'))",
     *   message="order.collection.not_available",
     *   groups={"cart"}
     * )
     */
    protected $takeaway = false;

    protected $vendors;

    protected $invitation;

    protected $loopeatDetails;

    protected ?OrderCredentials $loopeatCredentials = null;

    protected $businessAccount;

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

        if (null !== $customer && $this->hasLoopEatCredentials()) {

            WMAssert::isInstanceOf($this->customer, LoopeatOAuthCredentialsInterface::class);

            $this->customer->setLoopeatAccessToken($this->loopeatCredentials->getLoopeatAccessToken());
            $this->customer->setLoopeatRefreshToken($this->loopeatCredentials->getLoopeatRefreshToken());
            $this->clearLoopEatCredentials();
        }
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

    /**
     * @SerializedName("restaurant")
     */
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
     * @SerializedName("shippedAt")
     */
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

        if (!$restaurant->isDepositRefundEnabled()
            && !$restaurant->isLoopeatEnabled()
            && !$restaurant->isVytalEnabled()
            && !$restaurant->isDabbaEnabled()) {
            return false;
        }

        foreach ($this->getItems() as $item) {
            if ($item instanceof OrderItemInterface
            &&  $item->getVariant()->getProduct()->isReusablePackagingEnabled()) {

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

    /**
     * @SerializedName("fulfillmentMethod")
     */
    public function getFulfillmentMethod(): string
    {
        return $this->isTakeaway() ? 'collection' : 'delivery';
    }

    /**
     * @SerializedName("paymentMethod")
     */
    public function getPaymentMethod(): string
    {
        $payment = $this->getLastPayment();

        if ($payment && $payment->getMethod()) {
            return $payment->getMethod()->getCode();
        }

        return '';
    }

    /**
     * @SerializedName("fulfillmentMethod")
     */
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

    /**
     * @SerializedName("assignedTo")
     * @Groups({"order", "order_minimal"})
     */
    public function getAssignedTo()
    {
        if (null !== $this->getDelivery()) {
            $pickup = $this->getDelivery()->getPickup();

            if ($pickup->isAssigned()) {
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
                $hash[$restaurant] = array_merge($items, [ $item ]);
            }
        }

        return $hash;
    }

    /**
     * @SerializedName("adjustments")
     * @Groups({"order", "cart"})
     */
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

    /**
     * @return int
     */
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

    /**
     * @return float
     */
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

    public function getNotificationRecipients(): Collection
    {
        $recipients = new ArrayCollection();

        foreach ($this->getRestaurants() as $restaurant) {
            foreach ($restaurant->getOwners() as $owner) {
                $recipients->add($owner);
            }
        }

        return $recipients;
    }

    public function supportsGiropay(): bool
    {
        if ($this->isMultiVendor() || !$this->hasVendor()) {

            return false;
        }

        return $this->getRestaurant()->isStripePaymentMethodEnabled('giropay');
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
        if (null === $this->loopeatCredentials) {

            return null;
        }

        return $this->loopeatCredentials->getLoopeatAccessToken();
    }

    public function setLoopeatAccessToken($accessToken)
    {
        if (null === $this->loopeatCredentials) {

            $this->loopeatCredentials = new OrderCredentials();
            $this->loopeatCredentials->setOrder($this);
        }

        $this->loopeatCredentials->setLoopeatAccessToken($accessToken);
    }

    public function getLoopeatRefreshToken()
    {
        if (null === $this->loopeatCredentials) {

            return null;
        }

        return $this->loopeatCredentials->getLoopeatRefreshToken();
    }

    public function setLoopeatRefreshToken($refreshToken)
    {
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
        return $this->getRestaurants()->map(fn (LocalBusiness $restaurant): Address => $restaurant->getAddress());
    }
}
