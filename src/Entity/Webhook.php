<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Action\Webhook\Create as CreateController;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use League\Bundle\OAuth2ServerBundle\Model\Client;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'view\', object)'),
        new Delete(security: 'is_granted(\'edit\', object)'),
        new Post(
            controller: CreateController::class,
            normalizationContext: ['groups' => ['webhook', 'webhook_with_secret']],
            denormalizationContext: ['groups' => ['webhook_create']],
            securityPostDenormalize: 'is_granted(\'create\', object)'
        )
    ],
    normalizationContext: ['groups' => ['webhook']]
)]
class Webhook
{
    use Timestampable;

    public const EVENTS = [
        'delivery.assigned',
        'delivery.started',
        'delivery.failed',
        'delivery.picked',
        'delivery.in_transit',
        'delivery.completed',
        'order.created',
    ];

    private $id;

    /**
     * @var string
     */
    #[Groups(['webhook', 'webhook_create'])]
    private $url;

    /**
     * @var string
     */
    #[ApiProperty(openapiContext: ['type' => 'string', 'enum' => ['delivery.assigned', 'delivery.started', 'delivery.failed', 'delivery.picked', 'delivery.in_transit', 'delivery.completed', 'order.created']])]
    #[Assert\Choice(callback: 'getEvents')]
    #[Groups(['webhook', 'webhook_create'])]
    private $event;

    /**
     * @var Client
     */
    private $oauth2Client;

    /**
     * @var string
     */
    #[Groups(['webhook_with_secret'])]
    private $secret;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     *
     * @return self
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return string
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * @param string $event
     *
     * @return self
     */
    public function setEvent($event)
    {
        $this->event = $event;

        return $this;
    }

    /**
     * @return Client
     */
    public function getOauth2Client()
    {
        return $this->oauth2Client;
    }

    /**
     * @return self
     */
    public function setOauth2Client(Client $oauth2Client)
    {
        $this->oauth2Client = $oauth2Client;

        return $this;
    }

    /**
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * @param string $secret
     *
     * @return self
     */
    public function setSecret($secret)
    {
        $this->secret = $secret;

        return $this;
    }

    public static function getEvents()
    {
        return self::EVENTS;
    }
}
