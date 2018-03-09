<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Model\TaxableTrait;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Table(uniqueConstraints={
 *   @ORM\UniqueConstraint(name="stripe_payment_unique", columns={"resource_class", "resource_id"})}
 * )
 */
class StripePayment
{
    use TaxableTrait;

    const STATUS_PENDING = 'PENDING';
    const STATUS_CAPTURED = 'CAPTURED';

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var stringt
     *
     * @ORM\Column("uuid")
     */
    private $uuid;

    /**
     * @ORM\ManyToOne(targetEntity="ApiUser")
     */
    protected $user;

    /**
     * @ORM\Column(type="string")
     */
    protected $resourceClass;

    /**
     * @ORM\Column(type="integer")
     */
    protected $resourceId;

    /**
     * @ORM\Column(type="string")
     */
    protected $status = self::STATUS_PENDING;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $charge;

    /**
     * @ORM\PrePersist()
     */
    public function prePersist() {
        $this->uuid = Uuid::uuid4()->toString();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getUuid()
    {
        return $this->uuid;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser(ApiUser $user)
    {
        $this->user = $user;

        return $this;
    }

    public function getResourceClass()
    {
        return $this->resourceClass;
    }

    public function setResourceClass($resourceClass)
    {
        $this->resourceClass = $resourceClass;

        return $this;
    }

    public function getResourceId()
    {
        return $this->resourceId;
    }

    public function setResourceId($resourceId)
    {
        $this->resourceId = $resourceId;

        return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;

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

    public static function create(ApiUser $user, $resource)
    {
        // TODO Throw Exception if resource has no id

        $stripePayment = new self();

        $stripePayment->setUser($user);
        $stripePayment->setResourceClass(ClassUtils::getClass($resource));
        $stripePayment->setResourceId($resource->getId());
        $stripePayment->setTotalExcludingTax($resource->getTotalExcludingTax());
        $stripePayment->setTotalTax($resource->getTotalTax());
        $stripePayment->setTotalIncludingTax($resource->getTotalIncludingTax());

        return $stripePayment;
    }
}
