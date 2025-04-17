<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Core\Action\NotFoundAction;
use AppBundle\Action\Delivery\CalculateRetailPrice as CalculateController;
use AppBundle\Api\Dto\DeliveryInput;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ApiResource(operations: [new Get(controller: NotFoundAction::class, read: false, output: false), new Post(uriTemplate: '/retail_prices/calculate', input: DeliveryInput::class, controller: CalculateRetailPrice::class, status: 200, write: false, denormalizationContext: ['groups' => ['pricing_deliveries']], security: 'is_granted(\'ROLE_DISPATCHER\') or is_granted(\'ROLE_STORE\') or is_granted(\'ROLE_OAUTH2_DELIVERIES\')', openapiContext: ['summary' => 'Calculates price of a Delivery'])], normalizationContext: ['groups' => ['pricing_deliveries']])]
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

    /**
     * @var string
     */
    #[Groups(['pricing_deliveries'])]
    public $currency;

    /**
     * @var int
     */
    public $taxAmount;

    private bool $taxIncluded;

    public function __construct(int $taxIncludedAmount, string $currency, int $taxAmount, bool $taxIncluded = true)
    {
        $this->id = Uuid::uuid4()->toString();
        $this->amount = $taxIncluded ? $taxIncludedAmount : ($taxIncludedAmount - $taxAmount);
        $this->currency = $currency;
        $this->taxAmount = $taxAmount;
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
