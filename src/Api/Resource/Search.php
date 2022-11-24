<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Core\Action\NotFoundAction;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Search\ShopsProducts as SearchShopsProducts;

/**
 * @ApiResource(
 *   collectionOperations={
 *     "search_frontend"={
 *       "method"="GET",
 *       "path"="/search/shops_products",
 *       "read"=false,
 *       "controller"=SearchShopsProducts::class
 *     }
 *   },
 *   itemOperations={
 *     "get": {
 *       "method"="GET",
 *       "controller"=NotFoundAction::class,
 *       "read"=false,
 *       "output"=false
 *     }
 *   }
 * )
 */
final class Search
{
	/**
     * @var string
     *
     * @ApiProperty(identifier=true)
     */
    public $id;

    public function __construct()
    {
    }
}
