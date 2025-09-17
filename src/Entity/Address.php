<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Action\CreateAddress;
use AppBundle\Api\State\StoreAddressesProvider;
use AppBundle\Entity\Base\BaseAddress;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Store;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * @see http://schema.org/Place Documentation on Schema.org
 */
#[ApiResource(
    types: ['http://schema.org/Place'],
    operations: [
        new Get(security: 'is_granted(\'ROLE_ADMIN\')'),
        new Patch(security: 'is_granted(\'edit\', object)'),
        new GetCollection(security: 'is_granted(\'ROLE_ADMIN\')'),
        new Post(uriTemplate: '/me/addresses', controller: CreateAddress::class)
    ],
    normalizationContext: ['groups' => ['address']]
)]
#[ApiResource(
    uriTemplate: '/stores/{id}/addresses',
    types: ['http://schema.org/Place'],
    operations: [new GetCollection()],
    uriVariables: [
        'id' => new Link(fromClass: Store::class, fromProperty: 'addresses')
    ],
    status: 200,
    normalizationContext: ['groups' => ['address']],
    provider: StoreAddressesProvider::class
)]
class Address extends BaseAddress
{

    const PROVIDER_MAPPICKER = 'MAP_PICKER';
    const PROVIDERS = [
        self::PROVIDER_MAPPICKER
    ];

    /**
     * @var int
     */
    private $id;
    private $company;
    #[Groups(['task', 'warehouse', 'delivery', 'delivery_create', 'task_create', 'task_edit', 'address'])]
    private $contactName;
    private $complete = false;

    private ?string $provider = null;

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
    #[SerializedName('firstName')]
    #[Groups(['task'])]
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
    #[SerializedName('lastName')]
    #[Groups(['task'])]
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
    #[SerializedName('latLng')]
    #[Groups(['delivery_create', 'pricing_deliveries'])]
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

    #[Groups(['address', 'address_create', 'task', 'task_create', 'task_edit', 'order_update', 'restaurant_delivery'])]
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): void
    {
        $this->provider = $provider;
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
        $address->setProvider($this->provider);

        return $address;
    }
}
