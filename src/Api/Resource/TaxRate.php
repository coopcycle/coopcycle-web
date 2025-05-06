<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Action\NotFoundAction;
use AppBundle\Api\State\TaxRateProvider;
use AppBundle\Entity\Sylius\TaxRate as BaseTaxRate;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ApiResource(
    operations: [
        new Get(controller: NotFoundAction::class, read: false, output: false),
        new GetCollection(uriTemplate: '/tax_rates', provider: TaxRateProvider::class)
    ]
)]
final class TaxRate
{
    /**
     * @var string
     */
    #[ApiProperty(identifier: true)]
    public $id;

    public $code;

    public $amount;

    public $name;

    public $category;

    public $alternatives = [];

    public function __construct(BaseTaxRate $taxRate, string $name, array $alternatives = [])
    {
        $this->id = $taxRate->getCode();
        $this->code = $taxRate->getCode();
        $this->amount = $taxRate->getAmount();
        $this->name = $name;
        $this->category = $taxRate->getCategory()->getCode();
        $this->alternatives = $alternatives;
    }

    public function getId() {
        return $this->id;
    }
}
