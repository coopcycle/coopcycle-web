<?php

namespace AppBundle\Entity;

use Stripe\Refund;
use Stripe\PaymentIntent;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderAwareInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;

class StripePayment implements PaymentInterface, OrderAwareInterface
{
    protected $id;

    protected $order;

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
        if (isset($this->details['charge'])) {

            return $this->details['charge'];
        }
    }

    public function setCharge($charge)
    {
        $this->details = array_merge($this->details, ['charge' => $charge]);

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

    public function addRefund(Refund $refund)
    {
        $refunds = [];
        if (isset($this->details['refunds'])) {
            $refunds = $this->details['refunds'];
        }

        $refunds[] = [
            'id' => $refund->id,
            'amount' => $refund->amount,
        ];

        $this->details = array_merge($this->details, ['refunds' => $refunds]);
    }

    public function getRefunds()
    {
        if (isset($this->details['refunds'])) {

            return $this->details['refunds'];
        }

        return [];
    }

    public function getRefundTotal()
    {
        $total = 0;
        foreach ($this->getRefunds() as $refund) {
            $total += $refund['amount'];
        }

        return $total;
    }

    public function getRefundAmount()
    {
        return $this->getAmount() - $this->getRefundTotal();
    }

    public function setPaymentIntent(PaymentIntent $intent)
    {
        // Note that if your API version is before 2019-02-11, 'requires_action'
        // appears as 'requires_source_action'.

        $status = $intent->status;
        if ($intent->status === 'requires_source_action') {
            $status = 'requires_action';
        }

        $this->details = array_merge($this->details, [
            'payment_intent' => $intent->id,
            'payment_intent_client_secret' => $intent->client_secret,
            'payment_intent_status' => $status,
            'payment_intent_next_action' => $intent->next_action ? $intent->next_action->type : null
        ]);
    }

    public function getPaymentIntent()
    {
        if (isset($this->details['payment_intent'])) {

            return $this->details['payment_intent'];
        }
    }

    public function getPaymentIntentClientSecret()
    {
        if (isset($this->details['payment_intent_client_secret'])) {

            return $this->details['payment_intent_client_secret'];
        }
    }

    public function getPaymentIntentStatus()
    {
        if (isset($this->details['payment_intent_status'])) {

            return $this->details['payment_intent_status'];
        }
    }

    public function getPaymentIntentNextAction()
    {
        if (isset($this->details['payment_intent_next_action'])) {

            return $this->details['payment_intent_next_action'];
        }
    }

    public function setPaymentMethod($value)
    {
        $this->details = array_merge($this->details, [
            'payment_method' => $value,
        ]);
    }

    public function getPaymentMethod()
    {
        if (isset($this->details['payment_method'])) {

            return $this->details['payment_method'];
        }
    }

    public function requiresUseStripeSDK()
    {
        return $this->getPaymentIntentStatus() === 'requires_action' &&
            $this->getPaymentIntentNextAction() === 'use_stripe_sdk';
    }

    public function requiresCapture()
    {
        return $this->getPaymentIntentStatus() === 'requires_capture';
    }
}
