<?php

namespace AppBundle\Sylius\Customer;

use AppBundle\Entity\Address;
use AppBundle\Entity\User;
use AppBundle\LoopEat\OAuthCredentialsInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Collections\Collection;
use libphonenumber\PhoneNumber;
use Sylius\Component\Customer\Model\CustomerInterface as BaseCustomerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

interface CustomerInterface extends BaseCustomerInterface, OAuthCredentialsInterface
{
    /**
     * @return Collection|OrderInterface[]
     */
    public function getOrders(): Collection;

    /**
     * @return Address|null
     */
    public function getDefaultAddress(): ?Address;

    /**
     * @param Address|null $defaultAddress
     */
    public function setDefaultAddress(?Address $defaultAddress): void;

    /**
     * @param Address $address
     */
    public function addAddress(Address $address): void;

    /**
     * @param Address $address
     */
    public function removeAddress(Address $address): void;

    /**
     * @param Address $address
     *
     * @return bool
     */
    public function hasAddress(Address $address): bool;

    /**
     * @return Collection|Address[]
     */
    public function getAddresses(): Collection;

    /**
     * @return bool
     */
    public function hasUser(): bool;

    /**
     * @return User|UserInterface|null
     */
    public function getUser(): ?UserInterface;

    /**
     * @param User|UserInterface|null $user
     */
    public function setUser(?UserInterface $user);

    /**
     * @param PhoneNumber|string $telephone
     */
    public function setTelephone($telephone);
}
