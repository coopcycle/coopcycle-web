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
use ApiPlatform\Action\NotFoundAction;
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
            output: false,
            read: false
        ),
        new Delete(
            uriTemplate: '/woopit/deliveries/{deliveryId}',
            controller: DeliveryCancelController::class,
            openapiContext: ['summary' => 'Cancel a delivery.'],
            security: 'is_granted(\'ROLE_API_KEY\')',
            read: false,
            write: false
        ),
        new Patch(
            uriTemplate: '/woopit/deliveries/{deliveryId}',
            status: 204,
            controller: DeliveryUpdateController::class,
            openapiContext: ['summary' => 'Receives requests to update a delivery.'],
            normalizationContext: ['groups' => ['woopit_delivery_output']],
            denormalizationContext: ['groups' => ['woopit_delivery_input']],
            security: 'is_granted(\'ROLE_API_KEY\')',
            read: false,
            write: false
        ),
        new Post(
            uriTemplate: '/woopit/quotes',
            status: 201,
            controller: QuoteRequestController::class,
            openapiContext: ['summary' => 'Receives requests for quotes.'],
            normalizationContext: ['groups' => ['woopit_quote_output']],
            denormalizationContext: ['groups' => ['woopit_quote_input']],
            security: 'is_granted(\'ROLE_API_KEY\')'
        ),
        new Post(
            uriTemplate: '/woopit/deliveries',
            status: 201,
            controller: DeliveryRequestController::class,
            openapiContext: ['summary' => 'Receives requests for deliveries.'],
            normalizationContext: ['groups' => ['woopit_delivery_output']],
            denormalizationContext: ['groups' => ['woopit_delivery_input']],
            security: 'is_granted(\'ROLE_API_KEY\')',
            write: false
        ),
        new GetCollection(
            uriTemplate: '/woopit/quotes',
            controller: NotFoundAction::class,
            output: false,
            read: false
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
