<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Address;
use AppBundle\Entity\Dabba\CustomerCredentials as DabbaCustomerCredentials;
use AppBundle\Entity\LoopEat\CustomerCredentials;
use AppBundle\Entity\Edenred\CustomerCredentials as EdenredCustomerCredentials;
use AppBundle\Entity\User;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Sylius\Component\Customer\Model\Customer as BaseCustomer;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Webmozart\Assert\Assert;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Model\TaggableTrait;

/**
 * @ApiResource(
 *   shortName="Customer",
 *   normalizationContext={"groups"={"customer"}},
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_ADMIN') or user.getCustomer() == object"
 *     },
 *     "put"={
 *       "method"="PUT",
 *       "access_control"="is_granted('ROLE_ADMIN') or user.getCustomer() == object",
 *       "denormalization_context"={"groups"={"customer_update"}},
 *     }
 *   },
 *   collectionOperations={}
 * )
 */
class Customer extends BaseCustomer implements TaggableInterface, CustomerInterface
{
    use TaggableTrait;
    
    /** @var User */
    protected $user;

    /** @var Collection|OrderInterface[] */
    protected $orders;

    /** @var Address */
    protected $defaultAddress;

    /** @var Collection|Address[] */
    protected $addresses;

    protected ?CustomerCredentials $loopeatCredentials = null;

    protected ?EdenredCustomerCredentials $edenredCredentials = null;

    protected ?DabbaCustomerCredentials $dabbaCredentials = null;

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

        /** @var User|null $user */
        Assert::nullOrIsInstanceOf($user, User::class);

        $previousUser = $this->user;
        $this->user = $user;

        if ($previousUser instanceof User) {
            $previousUser->setCustomer(null);
        }

        if ($user instanceof User) {
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

    /**
     * @SerializedName("telephone")
     */
    public function getTelephone(): ?string
    {
        return $this->getPhoneNumber();
    }

    /**
     * @param PhoneNumber|string $telephone
     * @SerializedName("telephone")
     */
    public function setTelephone($telephone)
    {
        if ($telephone instanceof PhoneNumber) {
            $this->setPhoneNumber(
                PhoneNumberUtil::getInstance()->format($telephone, PhoneNumberFormat::E164)
            );
        } else {
            $this->setPhoneNumber($telephone);
        }
    }

    public function getUsername(): string
    {
        if ($this->hasUser()) {
            return $this->getUser()->getUsername();
        }

        return $this->getFullName();
    }

    public function getLoopeatAccessToken()
    {
        if (null === $this->loopeatCredentials) {

            return null;
        }

        return $this->loopeatCredentials->getLoopeatAccessToken();
    }

    public function setLoopeatAccessToken($accessToken)
    {
        if (null === $this->loopeatCredentials) {

            $this->loopeatCredentials = new CustomerCredentials();
            $this->loopeatCredentials->setCustomer($this);
        }

        $this->loopeatCredentials->setLoopeatAccessToken($accessToken);
    }

    public function getLoopeatRefreshToken()
    {
        if (null === $this->loopeatCredentials) {

            return null;
        }

        return $this->loopeatCredentials->getLoopeatRefreshToken();
    }

    public function setLoopeatRefreshToken($refreshToken)
    {
        if (null === $this->loopeatCredentials) {

            $this->loopeatCredentials = new CustomerCredentials();
            $this->loopeatCredentials->setCustomer($this);
        }

        $this->loopeatCredentials->setLoopeatRefreshToken($refreshToken);
    }

    public function hasLoopEatCredentials(): bool
    {
        return null !== $this->loopeatCredentials && $this->loopeatCredentials->hasLoopEatCredentials();
    }

    public function clearLoopEatCredentials()
    {
        if (null === $this->loopeatCredentials) {

            return;
        }

        $this->loopeatCredentials->setCustomer(null);
        $this->loopeatCredentials = null;
    }

    public function setFullName(?string $fullName): void
    {
        $this->setFirstName($fullName);
        $this->setLastName('');
    }

    public function getEdenredCredentials(): ?EdenredCustomerCredentials
    {
        return $this->edenredCredentials;
    }

    public function hasEdenredCredentials(): bool
    {
        return null !== $this->edenredCredentials &&
            ($this->edenredCredentials->getAccessToken() !== null && $this->edenredCredentials->getRefreshToken());
    }

    public function setEdenredAccessToken($accessToken)
    {
        if (null === $this->edenredCredentials) {

            $this->edenredCredentials = new EdenredCustomerCredentials();
            $this->edenredCredentials->setCustomer($this);
        }

        $this->edenredCredentials->setAccessToken($accessToken);
    }

    public function setEdenredRefreshToken($refreshToken)
    {
        if (null === $this->edenredCredentials) {

            $this->edenredCredentials = new EdenredCustomerCredentials();
            $this->edenredCredentials->setCustomer($this);
        }

        $this->edenredCredentials->setRefreshToken($refreshToken);
    }

    public function clearEdenredCredentials()
    {
        if (null === $this->edenredCredentials) {

            return;
        }

        $this->edenredCredentials->setCustomer(null);
        $this->edenredCredentials = null;
    }

    public function getDabbaAccessToken()
    {
        if (null == $this->dabbaCredentials) {

            return null;
        }

        return $this->dabbaCredentials->getAccessToken();
    }

    public function setDabbaAccessToken($accessToken)
    {
        if (null === $this->dabbaCredentials) {

            $this->dabbaCredentials = new DabbaCustomerCredentials();
            $this->dabbaCredentials->setCustomer($this);
        }

        $this->dabbaCredentials->setAccessToken($accessToken);
    }

    public function getDabbaRefreshToken()
    {
        if (null === $this->dabbaCredentials) {

            return null;
        }

        return $this->dabbaCredentials->getRefreshToken();
    }

    public function setDabbaRefreshToken($refreshToken)
    {
        if (null === $this->dabbaCredentials) {

            $this->dabbaCredentials = new DabbaCustomerCredentials();
            $this->dabbaCredentials->setCustomer($this);
        }

        $this->dabbaCredentials->setRefreshToken($refreshToken);
    }

    public function hasDabbaCredentials(): bool
    {
        return null !== $this->dabbaCredentials && $this->dabbaCredentials->hasCredentials();
    }

    public function clearDabbaCredentials()
    {
        if (null === $this->dabbaCredentials) {

            return;
        }

        $this->dabbaCredentials->setCustomer(null);
        $this->dabbaCredentials = null;
    }
}
