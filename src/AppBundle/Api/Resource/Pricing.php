<?php

namespace AppBundle\Api\Resource;

use AppBundle\Action\Delivery\Pricing as PricingController;
use AppBundle\Api\Dto\DeliveryInput;
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
 *       "write"=false,
 *       "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_STORE')"
 *     },
 *   },
 *   itemOperations={},
 * )
 */
final class Pricing
{
    public $price;
}
