<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Api\Dto\CartSessionInput;
use AppBundle\Api\State\CartSessionProcessor;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/carts/session',
            input: CartSessionInput::class,
            processor: CartSessionProcessor::class,
            status: 200,
            normalizationContext: ['groups' => ['cart']]
        )
    ]
)]
final class CartSession
{
    #[Groups(['cart'])]
    public $token;

    #[Groups(['cart'])]
    public $cart;
}
