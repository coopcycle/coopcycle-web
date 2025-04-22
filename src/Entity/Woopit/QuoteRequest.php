<?php

namespace AppBundle\Entity\Woopit;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Core\Action\NotFoundAction;
use AppBundle\Action\Woopit\QuoteRequest as QuoteRequestController;
use AppBundle\Action\Woopit\DeliveryRequest as DeliveryRequestController;
use AppBundle\Action\Woopit\DeliveryCancel as DeliveryCancelController;
use AppBundle\Action\Woopit\DeliveryUpdate as DeliveryUpdateController;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            controller: NotFoundAction::class,
            read: false,
            output: false
        ),
        new Delete(
            uriTemplate: '/woopit/deliveries/{deliveryId}',
            controller: DeliveryCancelController::class,
            security: 'is_granted(\'ROLE_API_KEY\')',
            read: false,
            write: false,
            openapiContext: ['summary' => 'Cancel a delivery.']
        ),
        new Patch(
            uriTemplate: '/woopit/deliveries/{deliveryId}',
            controller: DeliveryUpdateController::class,
            security: 'is_granted(\'ROLE_API_KEY\')',
            status: 204,
            read: false,
            write: false,
            denormalizationContext: ['groups' => ['woopit_delivery_input']],
            normalizationContext: ['groups' => ['woopit_delivery_output']],
            openapiContext: ['summary' => 'Receives requests to update a delivery.']
        ),
        new Post(
            uriTemplate: '/woopit/quotes',
            controller: QuoteRequestController::class,
            security: 'is_granted(\'ROLE_API_KEY\')',
            status: 201,
            denormalizationContext: ['groups' => ['woopit_quote_input']],
            normalizationContext: ['groups' => ['woopit_quote_output']],
            openapiContext: ['summary' => 'Receives requests for quotes.']
        ),
        new Post(
            uriTemplate: '/woopit/deliveries',
            controller: DeliveryRequestController::class,
            security: 'is_granted(\'ROLE_API_KEY\')',
            status: 201,
            write: false,
            denormalizationContext: ['groups' => ['woopit_delivery_input']],
            normalizationContext: ['groups' => ['woopit_delivery_output']],
            openapiContext: ['summary' => 'Receives requests for deliveries.']
        ),
        new GetCollection(
            controller: NotFoundAction::class,
            uriTemplate: '/woopit/quotes',
            read: false,
            output: false
        )
    ],
    formats: ['json']
)]
class QuoteRequest
{
    use Timestampable;

    const STATE_NEW = 'new';
    const STATE_CONFIRMED = 'confirmed';
    const STATE_CANCELLED = 'cancelled';

    public $id;

    #[Groups(['woopit_quote_input'])]
    public $orderId;

    #[Groups(['woopit_quote_input'])]
    public $referenceNumber;

    #[Groups(['woopit_quote_input', 'woopit_delivery_input'])]
    public $picking;

    #[Groups(['woopit_quote_input', 'woopit_delivery_input'])]
    public $delivery;

    #[Groups(['woopit_quote_input', 'woopit_delivery_input'])]
    public $packages;

    #[Groups(['woopit_quote_output', 'woopit_delivery_input'])]
    public $quoteId;

    public $price;

    public $priceDetails;

    public $state = self::STATE_NEW;

    #[Groups(['woopit_quote_output'])]
    public $vehicleType = 'VEHICLE_TYPE_BIKE';

    public $deliveryObject;

    #[Groups(['woopit_delivery_output'])]
    public $deliveryId;

    #[Groups(['woopit_delivery_output'])]
    public $parcels = [];

    #[Groups(['woopit_delivery_output'])]
    public $labels = [];

    #[Groups(['woopit_quote_input', 'woopit_delivery_input'])]
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

    #[Groups(['woopit_quote_output'])]
    #[SerializedName('price')]
    public function getPriceDetails()
    {
        return $this->priceDetails;
    }
}
