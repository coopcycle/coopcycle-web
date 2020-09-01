<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Address;
use AppBundle\Entity\ApiUser;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Customer\Model\Customer as BaseCustomer;
use Symfony\Component\Security\Core\User\UserInterface;
use Webmozart\Assert\Assert;

/**
 * @ApiResource(
 *   shortName="Customer",
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_ADMIN') or user.getCustomer() == object"
 *     }
 *   }
 * )
 */
class Customer extends BaseCustomer implements CustomerInterface
{
    /** @var ApiUser */
    protected $user;

    /** @var Collection|OrderInterface[] */
    protected $orders;

    /** @var Address */
    protected $defaultAddress;

    /** @var Collection|Address[] */
    protected $addresses;


    public function __construct()
    {
        parent::__construct();

        $this->orders = new ArrayCollection();
        $this->addresses = new ArrayCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultAddress(): ?Address
    {
        return $this->defaultAddress;
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultAddress(?Address $defaultAddress): void
    {
        $this->defaultAddress = $defaultAddress;

        if (null !== $defaultAddress) {
            $this->addAddress($defaultAddress);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addAddress(Address $address): void
    {
        if (!$this->hasAddress($address)) {
            $this->addresses[] = $address;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeAddress(Address $address): void
    {
        $this->addresses->removeElement($address);
    }

    /**
     * {@inheritdoc}
     */
    public function hasAddress(Address $address): bool
    {
        return $this->addresses->contains($address);
    }

    /**
     * {@inheritdoc}
     */
    public function getAddresses(): Collection
    {
        return $this->addresses;
    }

    /**
     * {@inheritdoc}
     */
    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    /**
     * {@inheritdoc}
     */
    public function setUser(?UserInterface $user): void
    {
        if ($this->user === $user) {
            return;
        }

        /** @var ApiUser|null $user */
        Assert::nullOrIsInstanceOf($user, ApiUser::class);

        $previousUser = $this->user;
        $this->user = $user;

        if ($previousUser instanceof ApiUser) {
            $previousUser->setCustomer(null);
        }

        if ($user instanceof ApiUser) {
            $user->setCustomer($this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasUser(): bool
    {
        return null !== $this->user;
    }

    public function getTelephone(): ?string
    {
        return $this->getPhoneNumber();
    }

    public function getUsername(): string
    {
        if ($this->hasUser()) {
            return $this->getUser()->getUsername();
        }

        return $this->getFullName();
    }
}
