<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Core\Action\NotFoundAction;
use AppBundle\Action\CentrifugoToken as TokenController;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *   attributes={
 *     "normalization_context"={"groups"={"centrifugo"}}
 *   },
 *   itemOperations={
 *     "get_token"={
 *       "method"="GET",
 *       "path"="/centrifugo/token",
 *       "controller"=TokenController::class,
 *       "read"=false,
 *       "openapi_context"={
 *         "summary"="Retrieves Centrifugo token",
 *       }
 *     },
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
     * @var int
     *
     * @Groups({"centrifugo"})
     */
    public $token;

    /**
     * @var int
     *
     * @Groups({"centrifugo"})
     */
    public $namespace;
}
