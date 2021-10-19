<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Core\Action\NotFoundAction;
use AppBundle\Action\CentrifugoToken as TokenController;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *   itemOperations={
 *     "get_token"={
 *       "method"="GET",
 *       "path"="/centrifugo/token",
 *       "controller"=TokenController::class,
 *       "read"=false,
 *       "normalization_context"={"groups"={"centrifugo"}},
 *       "openapi_context"={
 *         "summary"="Retrieves Centrifugo token"
 *       }
 *     },
 *     "refresh_token"={
 *       "method"="POST",
 *       "path"="/centrifugo/token/refresh",
 *       "controller"=TokenController::class,
 *       "read"=false,
 *       "normalization_context"={"groups"={"centrifugo_refresh"}},
 *       "openapi_context"={
 *         "summary"="Refreshes Centrifugo token"
 *       }
 *     }
 *   },
 *   collectionOperations={}
 * )
 */
final class Centrifugo
{
    /**
     * @var string
     *
     * @ApiProperty(identifier=true)
     */
    public $id;

    /**
     * @var string
     *
     * @Groups({"centrifugo", "centrifugo_refresh"})
     */
    public $token;

    /**
     * @var string
     *
     * @Groups({"centrifugo"})
     */
    public $namespace;

    /**
     * @var string
     *
     * @Groups({"centrifugo_for_order"})
     */
    public $channel;
}
