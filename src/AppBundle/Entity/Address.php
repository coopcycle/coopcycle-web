<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Base\BaseAddress;
use ApiPlatform\Core\Annotation\ApiResource;


/**
 * @see http://schema.org/Place Documentation on Schema.org
 *
 * @ApiResource(iri="http://schema.org/Place",
 *   collectionOperations={
 *     "get"={"method"="GET"},
 *     "create_address"={"route_name"="create_address"}
 *   },
 *   itemOperations={
 *     "get"={"method"="GET"},
 *   },
 * )
 */
class Address extends BaseAddress
{
    /**
     * @var int
     */
    private $id;

    /**
     * Gets id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

}
