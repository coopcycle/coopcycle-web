<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Core\Action\NotFoundAction;
use AppBundle\Action\Delivery\CalculateRetailPrice as CalculateController;
use AppBundle\Action\Delivery\CalculationItem;
use AppBundle\Api\Dto\DeliveryInput;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Delivery\OrderItem;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ApiResource(
    collectionOperations: [
        'calc_price' => [
            'method' => 'POST',
            'path' => '/retail_prices/calculate',
            'input' => DeliveryInput::class,
            'controller' => CalculateController::class,
            'status' => 200,
            'write' => false,
            'denormalization_context' => ['groups' => ['pricing_deliveries']],
            'access_control' => "is_granted('ROLE_DISPATCHER') or is_granted('ROLE_STORE') or is_granted('ROLE_OAUTH2_DELIVERIES')",
            'openapi_context' => ['summary' => 'Calculates price of a Delivery']
        ]
    ],
    itemOperations: [
        'get' => [
            'method' => 'GET',
            'controller' => NotFoundAction::class,
            'read' => false,
            'output' => false
        ]
    ],
    attributes: [
        'normalization_context' => ['groups' => ['pricing_deliveries']]
    ]
)]
final class RetailPrice
{
    /**
     * @var string
     */
    #[ApiProperty(identifier: true)]
    public $id;

    /**
     * @var int
     */
    #[Groups(['pricing_deliveries'])]
    public $amount;

    private bool $taxIncluded;

    /**
     * @param OrderItem[] $items
     * @param CalculationItem[] $calculation
     */
    public function __construct(
        #[Groups(['pricing_deliveries'])]
        public readonly array $items,
        #[Groups(['pricing_deliveries'])]
        public readonly array $calculation,
        int $taxIncludedAmount,
        #[Groups(['pricing_deliveries'])]
        public readonly string $currency,
        public readonly int $taxAmount,
        bool $taxIncluded = true
    )
    {
        $this->id = Uuid::uuid4()->toString();
        $this->amount = $taxIncluded ? $taxIncludedAmount : ($taxIncludedAmount - $taxAmount);
        $this->taxIncluded = $taxIncluded;
    }

    #[Groups(['pricing_deliveries'])]
    #[SerializedName('tax')]
    public function getTax(): array
    {
        return [
            'amount' => $this->taxAmount,
            'included' => $this->taxIncluded,
        ];
    }
}
