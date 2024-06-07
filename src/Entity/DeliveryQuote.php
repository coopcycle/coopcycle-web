<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Action\NotFoundAction;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Delivery\ConfirmQuote as ConfirmDeliveryQuoteController;
use AppBundle\Action\Delivery\Quote as DeliveryQuoteController;
use AppBundle\Api\Dto\DeliveryInput;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * @ApiResource(
 *   collectionOperations={
 *     "create_quote"={
 *       "method"="POST",
 *       "path"="/deliveries/quotes",
 *       "input"=DeliveryInput::class,
 *       "controller"=DeliveryQuoteController::class,
 *       "normalization_context"={"groups"={"delivery_quote"}},
 *       "denormalization_context"={"groups"={"delivery_create", "pricing_deliveries"}},
 *       "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_STORE') or is_granted('ROLE_OAUTH2_DELIVERIES')",
 *       "openapi_context"={
 *         "summary"="Creates a delivery quote"
 *       }
 *     }
 *   },
 *   itemOperations={
 *     "get": {
 *       "method"="GET",
 *       "controller"=NotFoundAction::class,
 *       "read"=false,
 *       "output"=false
 *     },
 *     "confirm_quote"={
 *       "method"="PUT",
 *       "path"="/deliveries/quotes/{id}/confirm",
 *       "controller"=ConfirmDeliveryQuoteController::class,
 *       "normalization_context"={"groups"={"delivery_quote_confirm"}},
 *       "access_control"="is_granted('confirm', object)",
 *       "openapi_context"={
 *         "summary"="Confirms a delivery quote"
 *       }
 *     }
 *   }
 * )
 */
class DeliveryQuote
{
    use Timestampable;

    private $id;
    private $store;
    private $state;

    /**
     * @Groups({"delivery_quote"})
     */
    private $amount;
    private string $payload = '';

    /**
     * @Groups({"delivery_quote"})
     */
    private $expiresAt;
    private $currencyCode;

    const STATE_NEW = 'new';
    const STATE_CONFIRMED = 'confirmed';

    /**
     * @Groups({"delivery_quote_confirm"})
     */
    private $delivery;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getStore()
    {
        return $this->store;
    }

    /**
     * @param mixed $store
     *
     * @return self
     */
    public function setStore($store)
    {
        $this->store = $store;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param mixed $state
     *
     * @return self
     */
    public function setState($state)
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param mixed $amount
     *
     * @return self
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * @return string
     */
    public function getPayload(): string
    {
        return $this->payload;
    }

    /**
     * @param string $payload
     *
     * @return self
     */
    public function setPayload($payload)
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getExpiresAt()
    {
        return $this->expiresAt;
    }

    /**
     * @param mixed $expiresAt
     *
     * @return self
     */
    public function setExpiresAt($expiresAt)
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function setCurrencyCode($currencyCode)
    {
        $this->currencyCode = $currencyCode;
    }

    /**
     * @SerializedName("currency")
     * @Groups({"delivery_quote"})
     */
    public function getCurrencyCode()
    {
        return $this->currencyCode;
    }

    /**
     * @return mixed
     */
    public function getDelivery()
    {
        return $this->delivery;
    }

    /**
     * @param mixed $delivery
     *
     * @return self
     */
    public function setDelivery($delivery)
    {
        $this->delivery = $delivery;

        return $this;
    }
}
