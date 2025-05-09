<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Action\NotFoundAction;
use AppBundle\Action\Search\ShopsProducts as SearchShopsProducts;

#[ApiResource(
    operations: [
        new Get(
            controller: NotFoundAction::class,
            output: false,
            read: false
        ),
        new GetCollection(
            uriTemplate: '/search/shops_products',
            controller: SearchShopsProducts::class,
            read: false
        )
    ]
)]
final class Search
{
	/**
     * @var string
     */
    #[ApiProperty(identifier: true)]
    public $id;
}
