<?php

namespace AppBundle\Entity;

use AppBundle\Action\CreateAddress;
use AppBundle\Entity\Base\BaseAddress;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
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
 *   },
 *   subresourceOperations={
 *     "api_stores_addresses_get_subresource"={
 *       "method"="GET",
 *       "normalization_context"={"groups"={"address", "place", "delivery"}}
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

    /**
     * @SerializedName("firstName")
     * @Groups({"task"})
     */
    public function getFirstName()
    {
        if (!empty($this->contactName)) {
            [$firstName, $lastName] = explode(' ', $this->contactName, 2);

            return $firstName;
        }

        return null;
    }

    /**
     * @SerializedName("lastName")
     * @Groups({"task"})
     */
    public function getLastName()
    {
        if (!empty($this->contactName)) {
            [$firstName, $lastName] = explode(' ', $this->contactName, 2);

            return $lastName;
        }

        return null;
    }
}
