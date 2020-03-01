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
 *       "normalization_context"={"groups"={"user", "address", "api_app"}},
 *       "swagger_context"={
 *         "summary"="Retrieves information about the authenticated token",
 *         "responses"={
 *           "200"={
 *             "description"="Authenticated token information",
 *             "schema"={
 *               "type"="object",
 *               "properties"={
 *                 "addresses"={"type"="array","items"={"$ref"="#/definitions/Address"}},
 *                 "username"={"type"="string"},
 *                 "email"={"type"="string"},
 *                 "roles"={"type"="array","items"={"type"="string"}},
 *               }
 *             }
 *           }
 *         }
 *       }
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
