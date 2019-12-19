<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Store;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;
use Trikoder\Bundle\OAuth2Bundle\Model\Client;

/**
 * @see https://schema.org/SoftwareApplication Documentation on Schema.org
 *
 * @ApiResource(iri="http://schema.org/SoftwareApplication",
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "security"="is_granted('ROLE_ADMIN')"
 *     }
 *   },
 *   attributes={
 *     "normalization_context"={"groups"={"api_app"}},
 *   }
 * )
 */
class ApiApp
{
    use Timestampable;

    private $id;
    private $name;
    private $oauth2Client;

    /**
     * @Groups({"api_app"})
     */
    private $store;

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

    public function getStore()
    {
        return $this->store;
    }

    public function setStore(Store $store)
    {
        $this->store = $store;

        return $this;
    }
}
