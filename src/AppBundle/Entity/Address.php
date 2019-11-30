<?php

namespace AppBundle\Entity;

use AppBundle\Action\CreateAddress;
use AppBundle\Entity\Base\BaseAddress;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Core\Annotation\ApiResource;

/**
 * @see http://schema.org/Place Documentation on Schema.org
 *
 * @ApiResource(iri="http://schema.org/Place",
 *   collectionOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_ADMIN')"
 *     },
 *     "create_address"={
 *       "method"="POST",
 *       "path"="/me/addresses",
 *       "controller"=CreateAddress::class
 *     }
 *   },
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_ADMIN')"
 *     }
 *   }
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
     * @Groups({"delivery", "delivery_create"})
     */
    private $contactName;

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

    /**
     * @return mixed
     */
    public function getContactName()
    {
        return $this->contactName;
    }

    /**
     * @param mixed $contactName
     *
     * @return self
     */
    public function setContactName($contactName)
    {
        $this->contactName = $contactName;

        return $this;
    }
}
