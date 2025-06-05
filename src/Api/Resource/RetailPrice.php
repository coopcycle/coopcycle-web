<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Action\NotFoundAction;
use AppBundle\Action\Delivery\CalculateRetailPrice as CalculateController;
use AppBundle\Api\Dto\CalculationOutput;
use AppBundle\Api\Dto\DeliveryDto;
use AppBundle\Entity\Delivery\OrderItem;
use AppBundle\Api\State\CalculateRetailPriceProcessor;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            controller: NotFoundAction::class,
            output: false,
            read: false
        ),
        new Post(
            uriTemplate: '/retail_prices/calculate',
            status: 200,
            openapiContext: ['summary' => 'Calculates price of a Delivery'],
            denormalizationContext: ['groups' => ['pricing_deliveries']],
            security: 'is_granted(\'ROLE_DISPATCHER\') or is_granted(\'ROLE_STORE\') or is_granted(\'ROLE_OAUTH2_DELIVERIES\')',
            input: DeliveryDto::class,
            processor: CalculateRetailPriceProcessor::class
        )
    ],
    normalizationContext: ['groups' => ['pricing_deliveries']]
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
     */
    public function __construct(
        #[Groups(['pricing_deliveries'])]
        public readonly array $items,
        #[Groups(['pricing_deliveries'])]
        public readonly CalculationOutput $calculation,
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
