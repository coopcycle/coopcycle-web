<?php

namespace AppBundle\Api\Resource;

use AppBundle\Action\Cart\CreateSession as CartSessionController;
use AppBundle\Api\Dto\CartSessionInput;
use ApiPlatform\Core\Annotation\ApiResource;

/**
 * @ApiResource(
 *   collectionOperations={
 *     "create_session"={
 *       "method"="POST",
 *       "path"="/carts/session",
 *       "controller"=CartSessionController::class,
 *       "write"=false,
 *       "input"=CartSessionInput::class
 *     }
 *   },
 *   itemOperations={}
 * )
 */
final class CartSession
{
    public $token;
    public $cart;
}
