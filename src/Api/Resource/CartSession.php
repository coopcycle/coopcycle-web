<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Action\Cart\CreateSession as CartSessionController;
use AppBundle\Api\Dto\CartSessionInput;

#[ApiResource(operations: [new Post(uriTemplate: '/carts/session', controller: CreateSession::class, write: false, input: CartSessionInput::class)])]
final class CartSession
{
    public $token;
    public $cart;
}
