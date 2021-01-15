<?php

namespace AppBundle\Api\Dto;

use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *   collectionOperations={
 *     "get"={
 *       "path"="/pricing/calculate-price",
 *     },
 *   },
 *   itemOperations={},
 * )
 */
final class CalculatePriceRequest
{
    // FIXME This is needed by PropertyInfo
    public $dropoffAddress;
}
