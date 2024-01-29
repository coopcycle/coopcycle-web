<?php

namespace AppBundle\Entity\Sling;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Sling\ToCSV;

/**
 * @ApiResource(iri="http://schema.org/SoftwareApplication",
 *     collectionOperations={
 *         "get"={
 *             "method"="GET",
 *             "access_control"="is_granted('ROLE_DISPATCHER') or is_granted('ROLE_ADMIN')",
 *             "pagination_enabled"=false
 *      },
 *         "to_csv"={
 *             "method"="POST",
 *             "path"="/sling/csv",
 *             "controller"=ToCSV::class
 *         }
 *     }
 * )
 */
class Sling
{

    /**
     * @ApiProperty(identifier=true)
     */
    private $_;

}
