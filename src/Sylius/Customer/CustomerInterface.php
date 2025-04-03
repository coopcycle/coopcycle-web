<?php

namespace AppBundle\Sylius\Customer;

use AppBundle\Dabba\OAuthCredentialsInterface as DabbaCredentialsInterface;
use AppBundle\Entity\Edenred\CustomerCredentials as EdenredCustomerCredentials;
use AppBundle\Entity\Address;
use AppBundle\Entity\User;
use AppBundle\LoopEat\OAuthCredentialsInterface as LoopeatCredentialsInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Collections\Collection;
use libphonenumber\PhoneNumber;
use Sylius\Component\Customer\Model\CustomerInterface as BaseCustomerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

interface CustomerInterface extends BaseCustomerInterface, LoopeatCredentialsInterface, DabbaCredentialsInterface
{
    /**
     * @return Collection|OrderInterface[]
     */
    public function getOrders(): Collection;

    public function getDefaultAddress(): ?Address;

    public function setDefaultAddress(?Address $defaultAddress): void;

    public function addAddress(Address $address): void;

    public function removeAddress(Address $address): void;

    
    public function hasAddress(Address $address): bool;

    /**
     * @return Collection|Address[]
     */
    public function getAddresses(): Collection;

    public function hasUser(): bool;

    public function getUser(): ?UserInterface;

    public function setUser(?UserInterface $user);

    /**
     * @param PhoneNumber|string $telephone
     */
    public function setTelephone($telephone);

    public function hasEdenredCredentials(): bool;

    public function getEdenredCredentials(): ?EdenredCustomerCredentials;

    public function setEdenredAccessToken($accessToken);

    public function setEdenredRefreshToken($refreshToken);
}
