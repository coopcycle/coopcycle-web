<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use AppBundle\Api\Filter\OrderDateFilter;

/**
 * @ApiResource(iri="http://schema.org/Order",
 *   collectionOperations={
 *     "get"={
 *       "method"="GET",
 *       "security"="is_granted('ROLE_ADMIN')"
 *     },
 *   },
 *   itemOperations={},
 *   attributes={
 *     "normalization_context"={"groups"={"order", "address"}}
 *   }
 * )
 * @ApiFilter(OrderDateFilter::class, properties={"date": "exact"})
 */
class InvoiceLineItem extends FrozenOrderLineItem
{
}

//TODO:
//* @ApiFilter(SearchFilter::class, properties={"state": "exact"})
