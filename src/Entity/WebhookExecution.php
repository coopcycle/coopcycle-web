<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Webhook\Create as CreateController;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class WebhookExecution
{
    use Timestampable;

    private $id;

    /**
     * @var Webhook
     */
    private $webhook;

    /**
     * @var int
     */
    private $statusCode;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Webhook
     */
    public function getWebhook()
    {
        return $this->webhook;
    }

    /**
     * @param Webhook $webhook
     *
     * @return self
     */
    public function setWebhook(Webhook $webhook)
    {
        $this->webhook = $webhook;

        return $this;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @param int $statusCode
     *
     * @return self
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;

        return $this;
    }
}
