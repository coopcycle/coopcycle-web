<?php

namespace AppBundle\Api\Resource;

use AppBundle\Action\Order\Adhoc as AdhocOrderController;
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
 *     }
 *   },
 *   itemOperations={}
 * )
 */
final class OrderAdhoc
{
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
