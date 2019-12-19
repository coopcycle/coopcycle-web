<?php

namespace AppBundle\Api\Resource;

use AppBundle\Action\Me as MeController;
use ApiPlatform\Core\Annotation\ApiResource;

/**
 * @ApiResource(
 *   collectionOperations={},
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "path"="/me",
 *       "controller"=MeController::class,
 *       "read"=false,
 *       "normalization_context"={"groups"={"user", "place", "api_app"}}
 *     }
 *   }
 * )
 */
final class Me
{
    // FIXME
    // Needed to avoid error
    // There is no PropertyInfo extractor supporting the class "AppBundle\Api\Resource\Me"
    public $foo;
}
