<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Base\BaseAddress;
use Symfony\Component\Serializer\Annotation\Groups;
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
     * @Groups({"address"})
     * @var int
     */
    private $id;

    /**
     * @Groups({"task"})
     */
    private $firstName;

    /**
     * @Groups({"task"})
     */
    private $lastName;

    private $company;

    /**
     * Gets id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function getFirstName()
    {
        return $this->firstName;
    }

    public function setFirstName($firstName)
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName()
    {
        return $this->lastName;
    }

    public function setLastName($lastName)
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getCompany()
    {
        return $this->company;
    }

    public function setCompany($company)
    {
        $this->company = $company;

        return $this;
    }
}
