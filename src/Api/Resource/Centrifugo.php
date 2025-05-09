<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Action\NotFoundAction;
use AppBundle\Action\CentrifugoToken as TokenController;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/centrifugo/token',
            controller: TokenController::class,
            openapiContext: ['summary' => 'Retrieves Centrifugo token'],
            normalizationContext: ['groups' => ['centrifugo']],
            read: false
        ),
        new Post(
            uriTemplate: '/centrifugo/token/refresh',
            controller: TokenController::class,
            openapiContext: ['summary' => 'Refreshes Centrifugo token'],
            normalizationContext: ['groups' => ['centrifugo_refresh']],
            read: false
        )
    ]
)]
final class Centrifugo
{
    /**
     * @var string
     */
    #[ApiProperty(identifier: true)]
    public $id;

    /**
     * @var string
     */
    #[Groups(['centrifugo', 'centrifugo_refresh'])]
    public $token;

    /**
     * @var string
     */
    #[Groups(['centrifugo'])]
    public $namespace;

    /**
     * @var string
     */
    #[Groups(['centrifugo_for_order'])]
    public $channel;
}
