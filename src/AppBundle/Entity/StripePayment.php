<?php

namespace AppBundle\Entity;

use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderAwareInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;

class StripePayment implements PaymentInterface, OrderAwareInterface
{
    protected $id;

    protected $order;

    protected $charge;

    protected $currencyCode;

    protected $amount = 0;

    protected $state = PaymentInterface::STATE_CART;

    protected $details = [];

    protected $createdAt;

    protected $updatedAt;

    public function getId()
    {
        return $this->id;
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    public function setOrder(?OrderInterface $order): void
    {
        $this->order = $order;
    }

    public function getCharge()
    {
        return $this->charge;
    }

    public function setCharge($charge)
    {
        $this->charge = $charge;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod(): ?PaymentMethodInterface
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    public function setMethod(?PaymentMethodInterface $method): void
    {
        $this->method = $method;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrencyCode(): ?string
    {
        return $this->currencyCode;
    }

    /**
     * {@inheritdoc}
     */
    public function setCurrencyCode(string $currencyCode): void
    {
        $this->currencyCode = $currencyCode;
    }

    /**
     * {@inheritdoc}
     */
    public function getAmount(): ?int
    {
        return $this->amount;
    }

    /**
     * {@inheritdoc}
     */
    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    /**
     * {@inheritdoc}
     */
    public function getState(): ?string
    {
        return $this->state;
    }

    /**
     * {@inheritdoc}
     */
    public function setState(string $state): void
    {
        $this->state = $state;
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * {@inheritdoc}
     */
    public function setDetails(array $details): void
    {
        $this->details = $details;
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * {@inheritdoc}
     */
    public function setCreatedAt(?\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * {@inheritdoc}
     */
    public function setUpdatedAt(?\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function setStripeUserId($stripeUserId)
    {
        $this->details = array_merge($this->details, ['stripe_user_id' => $stripeUserId]);
    }

    public function getStripeUserId()
    {
        if (isset($this->details['stripe_user_id'])) {
            return $this->details['stripe_user_id'];
        }
    }

    public function setStripeToken($stripeToken)
    {
        $this->details = array_merge($this->details, ['stripe_token' => $stripeToken]);
    }

    public function getStripeToken()
    {
        if (isset($this->details['stripe_token'])) {

            return $this->details['stripe_token'];
        }
    }

    public function setLastError($message)
    {
        $this->details = array_merge($this->details, ['last_error' => $message]);
    }

    public function getLastError()
    {
        if (isset($this->details['last_error'])) {

            return $this->details['last_error'];
        }
    }

    public static function create(OrderInterface $order)
    {
        $stripePayment = new self();

        $stripePayment->setOrder($order);
        $stripePayment->setAmount($order->getTotal());
        $stripePayment->setCurrencyCode('EUR');

        return $stripePayment;
    }
}
