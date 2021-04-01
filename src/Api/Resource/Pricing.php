<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Core\Action\NotFoundAction;
use AppBundle\Action\Delivery\Pricing as PricingController;
use AppBundle\Api\Dto\DeliveryInput;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *   collectionOperations={
 *     "calc_price"={
 *       "method"="POST",
 *       "path"="/pricing/deliveries",
 *       "input"=DeliveryInput::class,
 *       "controller"=PricingController::class,
 *       "status"=200,
 *       "write"=false,
 *       "denormalization_context"={"groups"={"delivery_create", "pricing_deliveries"}},
 *       "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_STORE')",
 *       "swagger_context"={
 *         "summary"="Calculates price of a Delivery",
 *       }
 *     },
 *   },
 *   itemOperations={
 *     "get": {
 *       "method"="GET",
 *       "controller"=NotFoundAction::class,
 *       "read"=false,
 *       "output"=false
 *     }
 *   }
 * )
 */
final class Pricing
{
    /**
     * @var string
     *
     * @ApiProperty(identifier=true)
     */
    public $id;

    /**
     * @var int
     */
    public $price;

    /**
     * @var string
     */
    public $currencyCode;
}
