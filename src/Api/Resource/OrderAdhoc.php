<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Action\Order\Adhoc as AdhocOrderController;
use AppBundle\Action\Order\AdhocUpdate as UpdateAdhocOrderController;
use AppBundle\Action\Order\SearchAdhoc as SearchAdhocOrdersController;
use AppBundle\Entity\LocalBusiness;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(operations: [new Get(uriTemplate: '/orders/adhoc/{id}'), new Put(uriTemplate: '/orders/adhoc/{id}', controller: AdhocUpdate::class, security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_RESTAURANT\')', read: false, write: false, validationContext: ['groups' => ['cart']], normalizationContext: ['groups' => ['order']]), new Post(uriTemplate: '/orders/adhoc', controller: Adhoc::class, security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_RESTAURANT\')', validationContext: ['groups' => ['cart']], normalizationContext: ['groups' => ['order']]), new GetCollection(uriTemplate: '/orders/adhoc/search', controller: SearchAdhoc::class, security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_RESTAURANT\')', normalizationContext: ['groups' => ['order']])])]
final class OrderAdhoc
{
    #[ApiProperty(identifier: true)]
    public $id;

    /**
     * @var LocalBusiness
     */
    #[Assert\NotBlank]
    public $restaurant;

    #[Assert\NotBlank]
    public $customer;

    /**
     * @var array
     */
    #[Assert\NotBlank]
    public $items;
}
