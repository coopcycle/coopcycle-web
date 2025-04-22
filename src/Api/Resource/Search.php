<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Core\Action\NotFoundAction;
use AppBundle\Action\Search\ShopsProducts as SearchShopsProducts;

#[ApiResource(operations: [new Get(controller: NotFoundAction::class, read: false, output: false), new GetCollection(uriTemplate: '/search/shops_products', read: false, controller: SearchShopsProducts::class)])]
final class Search
{
	/**
     * @var string
     */
    #[ApiProperty(identifier: true)]
    public $id;
}
