<?php

namespace AppBundle\Entity\Woopit;

use ApiPlatform\Core\Action\NotFoundAction;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Woopit\QuoteRequest as QuoteRequestController;
use AppBundle\Action\Woopit\DeliveryRequest as DeliveryRequestController;
use AppBundle\Action\Woopit\DeliveryCancel as DeliveryCancelController;
use AppBundle\Action\Woopit\DeliveryUpdate as DeliveryUpdateController;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * @ApiResource(
 *   formats={"json"},
 *   collectionOperations={
 *     "post"={
 *       "method"="POST",
 *       "path"="/woopit/quotes",
 *       "controller"=QuoteRequestController::class,
 *       "security"="is_granted('ROLE_API_KEY')",
 *       "status"=201,
 *       "denormalization_context"={"groups"={"woopit_quote_input"}},
 *       "normalization_context"={"groups"={"woopit_quote_output"}},
 *       "openapi_context"={
 *         "summary"="Receives requests for quotes.",
 *       }
 *     },
 *     "post_deliveries"={
 *       "method"="POST",
 *       "path"="/woopit/deliveries",
 *       "controller"=DeliveryRequestController::class,
 *       "security"="is_granted('ROLE_API_KEY')",
 *       "status"=201,
 *       "write"=false,
 *       "denormalization_context"={"groups"={"woopit_delivery_input"}},
 *       "normalization_context"={"groups"={"woopit_delivery_output"}},
 *       "openapi_context"={
 *         "summary"="Receives requests for deliveries.",
 *       }
 *     },
 *     "get": {
 *       "method"="GET",
 *       "controller"=NotFoundAction::class,
 *       "path"="/woopit/quotes",
 *       "read"=false,
 *       "output"=false
 *     }
 *   },
 *   itemOperations={
 *     "get": {
 *       "method"="GET",
 *       "controller"=NotFoundAction::class,
 *       "read"=false,
 *       "output"=false
 *     },
 *     "delete_deliveries"={
 *       "method"="DELETE",
 *       "path"="/woopit/deliveries/{deliveryId}",
 *       "controller"=DeliveryCancelController::class,
 *       "security"="is_granted('ROLE_API_KEY')",
 *       "read"=false,
 *       "write"=false,
 *       "openapi_context"={
 *         "summary"="Cancel a delivery.",
 *       }
 *     },
 *     "patch_deliveries"={
 *       "method"="PATCH",
 *       "path"="/woopit/deliveries/{deliveryId}",
 *       "controller"=DeliveryUpdateController::class,
 *       "security"="is_granted('ROLE_API_KEY')",
 *       "status"=204,
 *       "read"=false,
 *       "write"=false,
 *       "denormalization_context"={"groups"={"woopit_delivery_input"}},
 *       "normalization_context"={"groups"={"woopit_delivery_output"}},
 *       "openapi_context"={
 *         "summary"="Receives requests to update a delivery.",
 *       }
 *     },
 *   }
 * )
 */
class QuoteRequest
{
    use Timestampable;

    const STATE_NEW = 'new';
    const STATE_CONFIRMED = 'confirmed';
    const STATE_CANCELLED = 'cancelled';

    public $id;

    /**
     * @Groups({"woopit_quote_input"})
     */
    public $orderId;

    /**
     * @Groups({"woopit_quote_input"})
     */
    public $referenceNumber;

    /**
     * @Groups({"woopit_quote_input", "woopit_delivery_input"})
     */
    public $picking;

    /**
     * @Groups({"woopit_quote_input", "woopit_delivery_input"})
     */
    public $delivery;

    /**
     * @Groups({"woopit_quote_input", "woopit_delivery_input"})
     */
    public $packages;

    /**
     * @Groups({"woopit_quote_output", "woopit_delivery_input"})
     */
    public $quoteId;

    public $price;

    public $priceDetails;

    public $state = self::STATE_NEW;

    /**
     * @Groups({"woopit_quote_output"})
     */
    public $vehicleType = 'VEHICLE_TYPE_BIKE';

    public $deliveryObject;

    /**
     * @Groups({"woopit_delivery_output"})
     */
    public $deliveryId;

    /**
     * @Groups({"woopit_quote_input", "woopit_delivery_input"})
     */
    public $retailer;

    /**
     * Gets id.
     *
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @Groups({"woopit_quote_output"})
     * @SerializedName("price")
     */
    public function getPriceDetails()
    {
        return $this->priceDetails;
    }
}
