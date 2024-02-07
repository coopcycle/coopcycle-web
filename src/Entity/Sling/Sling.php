<?php

namespace AppBundle\Entity\Sling;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Sling\ToCSV;
use AppBundle\Action\Sling\SaveToken;

/**
 * @ApiResource(iri="http://schema.org/SoftwareApplication",
 *     collectionOperations={
 *         "get"={
 *             "method"="GET",
 *             "access_control"="is_granted('ROLE_DISPATCHER') or is_granted('ROLE_ADMIN')",
 *             "pagination_enabled"=false
 *          },
 *         "to_csv"={
 *             "method"="POST",
 *             "path"="/sling/csv",
 *             "controller"=ToCSV::class
 *         },
 *         "save_token"={
 *              "method"="POST",
 *              "path"="/sling/token",
 *              "controller"=SaveToken::class
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
