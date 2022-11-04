<?php

namespace AppBundle\Api\Resource;

use AppBundle\Action\Order\Adhoc as AdhocOrderController;
use AppBundle\Action\Order\AdhocUpdate as UpdateAdhocOrderController;
use AppBundle\Action\Order\SearchAdhoc as SearchAdhocOrdersController;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\LocalBusiness;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *   collectionOperations={
 *     "create"={
 *       "method"="POST",
 *       "path"="/orders/adhoc",
 *       "controller"=AdhocOrderController::class,
 *       "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_RESTAURANT')",
 *       "validation_groups"={"cart"},
 *       "normalization_context"={"groups"={"order"}}
 *     },
 *     "get"={
 *       "method"="GET",
 *       "path"="/orders/adhoc/search",
 *       "controller"=SearchAdhocOrdersController::class,
 *       "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_RESTAURANT')",
 *       "normalization_context"={"groups"={"order"}}
 *     }
 *   },
 *   itemOperations={
 *     "get": {
 *       "method"="GET",
 *       "path"="/orders/adhoc/{id}"
 *     },
 *     "add_items"={
 *       "method"="PUT",
 *       "path"="/orders/adhoc/{id}",
 *       "controller"=UpdateAdhocOrderController::class,
 *       "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_RESTAURANT')",
 *       "read"=false,
 *       "write"=false,
 *       "validation_groups"={"cart"},
 *       "normalization_context"={"groups"={"order"}}
 *     }
 *   }
 * )
 */
final class OrderAdhoc
{
    /**
     * @ApiProperty(identifier=true)
     */
    public $id;

    /**
     * @var LocalBusiness
     * @Assert\NotBlank
     */
    public $restaurant;

    /**
     * @Assert\NotBlank
     */
    public $customer;

    /**
     * @var array
     * @Assert\NotBlank
     */
    public $items;
}
