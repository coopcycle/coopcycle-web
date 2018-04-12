<?php

namespace AppBundle\Entity;

use phpDocumentor\Reflection\Types\Integer;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;

class StripeTransfer implements PaymentInterface
{

    protected $id;

    protected $stripePayment;

    protected $transfer;

    protected $transferGroup;

    protected $stripeAccount;

    protected $currencyCode;

    protected $amount = 0;

    protected $state = PaymentInterface::STATE_NEW;

    protected $details = [];

    protected $createdAt;

    protected $updatedAt;

    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getStripePayment()
    {
        return $this->stripePayment;
    }

    /**
     * @param mixed $stripePayment
     */
    public function setStripePayment($stripePayment)
    {
        $this->stripePayment = $stripePayment;
    }

    /**
     * @return mixed
     */
    public function getTransfer()
    {
        return $this->transfer;
    }

    /**
     * @param mixed $transfer
     */
    public function setTransfer($transfer)
    {
        $this->transfer = $transfer;
    }

    /**
     * @return mixed
     */
    public function getTransferGroup()
    {
        return $this->transferGroup;
    }

    /**
     * @param mixed $transferGroup
     */
    public function setTransferGroup($transferGroup)
    {
        $this->transferGroup = $transferGroup;
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

    public static function create(StripePayment $payment, $amount)
    {
        $stripeTransfer = new self();

        $stripeTransfer->setStripePayment($payment);
        $stripeTransfer->setAmount($amount);
        $stripeTransfer->setCurrencyCode('EUR');
        $stripeTransfer->setTransferGroup($payment->getCharge());

        return $stripeTransfer;
    }

}