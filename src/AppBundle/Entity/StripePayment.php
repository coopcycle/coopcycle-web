<?php

namespace AppBundle\Entity;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;

/**
 * @ORM\Entity
 */
class StripePayment implements PaymentInterface
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Sylius\Component\Order\Model\Order")
     */
    protected $order;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $charge;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $currencyCode;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $amount = 0;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $state = PaymentInterface::STATE_CART;

    /**
     * @var array
     * @ORM\Column(type="json_array")
     */
    protected $details = [];

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    protected $createdAt;

    /**
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     */
    protected $updatedAt;

    public function getId()
    {
        return $this->id;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function setOrder(OrderInterface $order)
    {
        $this->order = $order;

        return $this;
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

    public static function create(OrderInterface $order)
    {
        $stripePayment = new self();

        $stripePayment->setOrder($order);
        $stripePayment->setAmount($order->getTotal());
        $stripePayment->setCurrencyCode('EUR');

        return $stripePayment;
    }
}
