<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Store;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;
use League\Bundle\OAuth2ServerBundle\Model\Client;

/**
 * @see https://schema.org/SoftwareApplication Documentation on Schema.org
 */
#[ApiResource(
    types: ['http://schema.org/SoftwareApplication'],
    operations: [
        new Get(security: 'is_granted(\'ROLE_ADMIN\')')
    ],
    normalizationContext: ['groups' => ['api_app']]
)]
class ApiApp
{
    use Timestampable;

    private $id;

    /**
     * @var string
     */
    #[Groups(['api_app'])]
    private $name;

    /**
     * @var Client
     */
    private $oauth2Client;

    /**
     * @var Store|null
     */
    #[Groups(['api_app'])]
    private $store;

    /**
     * @var LocalBusiness|null
     */
    #[Groups(['api_app'])]
    private $shop;

    /**
     * @var string
     */
    private $type = 'oauth';

    /**
     * @var string|null
     */
    private $apiKey;

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getOauth2Client()
    {
        return $this->oauth2Client;
    }

    public function setOauth2Client(Client $oauth2Client)
    {
        $this->oauth2Client = $oauth2Client;

        return $this;
    }

    public function getStore(): ?Store
    {
        return $this->store;
    }

    public function setStore(Store $store)
    {
        $this->store = $store;

        return $this;
    }

    public function getShop(): ?LocalBusiness
    {
        return $this->shop;
    }

    public function setShop(LocalBusiness $shop)
    {
        $this->shop = $shop;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    public function getApiKey()
    {
        return $this->apiKey;
    }

    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;

        return $this;
    }
}
