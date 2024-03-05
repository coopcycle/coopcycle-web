<?php

namespace AppBundle\Entity;

use AppBundle\Action\CreateAddress;
use AppBundle\Entity\Base\BaseAddress;
use AppBundle\Entity\Base\GeoCoordinates;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use ApiPlatform\Core\Annotation\ApiResource;

/**
 * @see http://schema.org/Place Documentation on Schema.org
 *
 * @ApiResource(iri="http://schema.org/Place",
 *   attributes={
 *     "normalization_context"={"groups"={"address"}}
 *   },
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
 *       "normalization_context"={"groups"={"address", "delivery"}}
 *     }
 *   }
 * )
 */
class Address extends BaseAddress
{
    /**
     * @var int
     */
    private $id;

    private $company;

    /**
     * @Groups({"task", "delivery", "delivery_create", "task_create", "task_edit"})
     */
    private $contactName;


    private $complete;

    /**
     * Gets id.
     *
     * @return int|null
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

            $parts = explode(' ', $this->contactName, 2);
            if (count($parts) === 2) {
                [$firstName, $lastName] = $parts;

                return $firstName;
            }

            return $this->contactName;
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

            $parts = explode(' ', $this->contactName, 2);
            if (count($parts) === 2) {
                [$firstName, $lastName] = $parts;

                return $lastName;
            }

            return $this->contactName;
        }

        return null;
    }

    /**
     * @param array $latLng
     * @SerializedName("latLng")
     * @Groups({"delivery_create"})
     */
    public function setLatLng(array $latLng)
    {
        [ $lat, $lng ] = $latLng;

        $this->setGeo(new GeoCoordinates($lat, $lng));

        return $this;
    }

    public function setComplete(bool $complete): self
    {
        $this->complete = $complete;
        return $this;
    }

    public function getComplete(): bool
    {
        return $this->complete;
    }

    public function clone()
    {
        $address = new Address();

        $address->setDescription($this->description);
        $address->setCompany($this->company);
        $address->setContactName($this->contactName);
        $address->setGeo($this->getGeo());
        $address->setStreetAddress($this->streetAddress);
        $address->setAddressLocality($this->addressLocality);
        $address->setAddressCountry($this->addressCountry);
        $address->setAddressRegion($this->addressRegion);
        $address->setPostOfficeBoxNumber($this->postOfficeBoxNumber);
        $address->setTelephone($this->telephone);
        $address->setName($this->getName());
        $address->setPostalCode($this->postalCode);

        return $address;
    }
}
