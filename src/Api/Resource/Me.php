<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Action\Me as MeController;
use AppBundle\Action\DeleteMe;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me',
            controller: MeController::class,
            read: false,
            normalizationContext: ['groups' => ['user', 'address', 'api_app']],
            openapiContext: ['summary' => 'Retrieves information about the authenticated token', 'responses' => [['description' => 'Authenticated token information', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['addresses' => ['type' => 'array', 'items' => ['$ref' => '#/definitions/Address']], 'username' => ['type' => 'string'], 'email' => ['type' => 'string'], 'roles' => ['type' => 'array', 'items' => ['type' => 'string']]]]]]]]]
        ),
        new Delete(
            uriTemplate: '/me',
            controller: DeleteMe::class,
            read: false,
            write: false
        )
    ]
)]
final class Me
{
    // FIXME
    // Needed to avoid error
    // There is no PropertyInfo extractor supporting the class "AppBundle\Api\Resource\Me"
    // You should add #[\ApiPlatform\Metadata\ApiProperty(identifier: true)]" on the property identifying the resource
    #[ApiProperty(identifier: true)]
    public $foo;
}
