<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Core\Action\NotFoundAction;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
// use AppBundle\Action\TaxRate as TaxRateController;
use AppBundle\Entity\Sylius\TaxRate as BaseTaxRate;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * @ApiResource(
 *   collectionOperations={
 *     "tax_rates"={
 *       "method"="GET",
 *       "path"="/tax_rates"
 *     }
 *   },
 *   itemOperations={
 *     "get": {
 *       "method"="GET",
 *       "controller"=NotFoundAction::class,
 *       "read"=false,
 *       "output"=false
 *     },
 *   }
 * )
 */
final class TaxRate
{
    /**
     * @var string
     *
     * @ApiProperty(identifier=true)
     */
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
}
