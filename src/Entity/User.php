<?php

namespace AppBundle\Entity;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Me as MeController;
use AppBundle\Action\MyStripePaymentMethods;
use AppBundle\Api\Dto\StripePaymentMethodsOutput;
use AppBundle\Api\Filter\UserRoleFilter;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Product\ProductInterface;
use Nucleos\UserBundle\Model\User as BaseUser;
use Gedmo\Timestampable\Traits\Timestampable;
use Doctrine\Common\Collections\ArrayCollection;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;
use Sylius\Component\Channel\Model\ChannelAwareInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\LegacyPasswordAuthenticatedUserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *   shortName="User",
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_ADMIN') or user == object"
 *     },
 *     "put"={
 *       "method"="PUT",
 *       "access_control"="is_granted('ROLE_ADMIN') or user == object",
 *       "denormalization_context"={"groups"={"user_update"}},
 *     }
 *   },
 *   collectionOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_ADMIN')",
 *       "pagination_enabled"=false
 *     },
 *     "get_stripe_payment_methods"={
 *       "method"="GET",
 *       "path"="/me/stripe-payment-methods",
 *       "controller"=MyStripePaymentMethods::class,
 *       "output"=StripePaymentMethodsOutput::class,
 *       "normalization_context"={"api_sub_level"=true}
 *     }
 *   },
 *   attributes={
 *     "normalization_context"={ "groups"={"user", "order"} }
 *   }
 * )
 * @ApiFilter(UserRoleFilter::class, properties={"roles"})
 * @UniqueEntity("facebookId")
 */
class User extends BaseUser implements JWTUserInterface, ChannelAwareInterface, LegacyPasswordAuthenticatedUserInterface, \Serializable
{
    use Timestampable;

    /**
     * @Groups({"incident"})
     */
    protected $id;

    /**
     * @var string
     * @Groups({"incident"})
     */
    protected ?string $username;

    /**
     * @var string
     */
    protected ?string $email;

    private $restaurants;

    private $stores;

    private $stripeAccounts;

    private $remotePushTokens;

    protected $channel;

    protected $facebookId;

    protected $facebookAccessToken;

    protected $quotesAllowed = false;

    protected $defaultNonprofit;

    /**
     * @var CustomerInterface|null
     */
    protected $customer;

    protected $optinConsents;

    private $stripeCustomerId;

    protected ?string $salt = null;

    private $businessAccount;

    /**
     * Only to keep data in form flow
     */
    private $termsAndConditionsAndPrivacyPolicy;

    public function __construct()
    {
        $this->restaurants = new ArrayCollection();
        $this->stores = new ArrayCollection();
        $this->stripeAccounts = new ArrayCollection();
        $this->remotePushTokens = new ArrayCollection();
        $this->optinConsents = new ArrayCollection();

        parent::__construct();
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getGivenName()
    {
        if (null !== $this->customer) {
            return $this->customer->getFirstName();
        }
    }

    /**
     * @param mixed $givenName
     */
    public function setGivenName($givenName)
    {
        if (null !== $this->customer) {
            $this->customer->setFirstName($givenName);
        }
    }

    /**
     * @return mixed
     */
    public function getFamilyName()
    {
        if (null !== $this->customer) {
            return $this->customer->getLastName();
        }
    }

    /**
     * @param mixed $familyName
     */
    public function setFamilyName($familyName)
    {
        if (null !== $this->customer) {
            $this->customer->setLastName($familyName);
        }
    }

    /**
     * @return mixed
     */
    public function getTelephone()
    {
        if (null !== $this->customer) {

            $phoneNumber = $this->customer->getPhoneNumber();

            if (!empty($phoneNumber)) {
                try {
                    return PhoneNumberUtil::getInstance()->parse($phoneNumber);
                } catch (NumberParseException $e) {}
            }
        }
    }

    /**
     * @param PhoneNumber|string $telephone
     */
    public function setTelephone($telephone)
    {
        if (null !== $this->customer) {
            $this->customer->setTelephone($telephone);
        }
    }

    public function setFacebookId($facebookId)
    {
        $this->facebookId = $facebookId;
    }

    public function getFacebookId()
    {
        return $this->facebookId;
    }

    public function setFacebookAccessToken($facebookAccessToken)
    {
        $this->facebookAccessToken = $facebookAccessToken;
    }

    public function getFacebookAccessToken()
    {
        return $this->facebookAccessToken;
    }

    public function setRestaurants($restaurants)
    {
        $this->restaurants = $restaurants;

        return $this;
    }

    public function addRestaurant(LocalBusiness $restaurant)
    {
        if (!$this->restaurants->contains($restaurant)) {
            $this->restaurants->add($restaurant);
        }

        return $this;
    }

    public function ownsRestaurant(LocalBusiness $restaurant)
    {
        return $this->restaurants->contains($restaurant);
    }

    public function getRestaurants()
    {
        return $this->restaurants;
    }

    public function setStores($stores)
    {
        $this->stores = $stores;

        return $this;
    }

    public function addStore(Store $store)
    {
        $this->stores->add($store);

        return $this;
    }

    public function ownsStore(Store $store)
    {
        return $this->stores->contains($store);
    }

    public function getStores()
    {
        return $this->stores;
    }

    public function addAddress(Address $address)
    {
        $this->customer->addAddress($address);

        return $this;
    }

    public function removeAddress(Address $address)
    {
        $this->customer->removeAddress($address);

        return $this;
    }

    public function getAddresses()
    {
        return $this->customer->getAddresses();
    }

    public function getStripeAccounts()
    {
        return $this->stripeAccounts;
    }

    public function addStripeAccount(StripeAccount $stripeAccount)
    {
        $this->stripeAccounts->add($stripeAccount);

        return $this;
    }

    public function getRemotePushTokens()
    {
        return $this->remotePushTokens;
    }

    public function setRemotePushTokens($remotePushTokens)
    {
        $this->remotePushTokens = $remotePushTokens;

        return $this;
    }

    public function addRemotePushToken(RemotePushToken $remotePushToken)
    {
        $remotePushToken->setUser($this);

        $this->remotePushTokens->add($remotePushToken);

        return $this;
    }

    public function getFullName()
    {
        if (null !== $this->customer) {
            return $this->customer->getFullName();
        }
    }

    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    public function setChannel(?ChannelInterface $channel): void
    {
        $this->channel = $channel;
    }

    public static function createFromPayload($username, array $payload)
    {
        $user = new self();
        $user->setUsername($payload['username']);
        if (isset($payload['roles'])) {
            $user->setRoles($payload['roles']);
        }

        return $user;
    }

    /**
     * @return mixed
     */
    public function isQuotesAllowed()
    {
        return $this->quotesAllowed || $this->hasRole('ROLE_ADMIN');
    }

    /**
     * @param mixed $quotesAllowed
     *
     * @return self
     */
    public function setQuotesAllowed($quotesAllowed)
    {
        $this->quotesAllowed = $quotesAllowed;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomer(?CustomerInterface $customer): void
    {
        if ($this->customer === $customer) {
            return;
        }

        $previousCustomer = $this->customer;
        $this->customer = $customer;

        if ($previousCustomer instanceof CustomerInterface) {
            $previousCustomer->setUser(null);
        }

        if ($customer instanceof CustomerInterface) {
            $customer->setUser($this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setEmail(string $email): void
    {
        parent::setEmail($email);

        if (null !== $this->customer) {
            $this->customer->setEmail($email);
        }
    }

    public function getDefaultNonprofit()
    {
        return $this->defaultNonprofit;
    }

    public function setDefaultNonprofit($defaultNonprofit)
    {
        $this->defaultNonprofit = $defaultNonprofit;
    }

    /**
     * @return mixed
     */
    public function getOptinConsents()
    {
        return $this->optinConsents;
    }

    /**
     * @param mixed $optinConsents
     *
     * @return self
     */
    public function setOptinConsents($optinConsents)
    {
        $this->optinConsents = $optinConsents;

        return $this;
    }

    /**
     * @param mixed $optinConsent
     *
     * @return self
     */
    public function addOptinConsent($optinConsent)
    {
        $optinConsent->setUser($this);

        $this->optinConsents->add($optinConsent);

        return $this;
    }

    public function getStripeCustomerId()
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId($stripeCustomerId)
    {
        $this->stripeCustomerId = $stripeCustomerId;

        return $this;
    }

    public function getSalt(): ?string
    {
        return $this->salt;
    }

    public function getBusinessAccount(): ?BusinessAccount
    {
        return $this->businessAccount;
    }

    public function setBusinessAccount(?BusinessAccount $businessAccount)
    {
        $this->businessAccount = $businessAccount;
    }

    public function hasBusinessAccount(): bool
    {
        return null !== $this->businessAccount;
    }

    public function getTermsAndConditionsAndPrivacyPolicy() {
        return $this->termsAndConditionsAndPrivacyPolicy;
    }

    public function setTermsAndConditionsAndPrivacyPolicy($termsAndConditionsAndPrivacyPolicy) {
        $this->termsAndConditionsAndPrivacyPolicy = $termsAndConditionsAndPrivacyPolicy;
        return $this;
    }

    /**
     * @return mixed[]
     */
    public function __serialize(): array
    {
        return [
            $this->password,
            $this->salt,
            $this->usernameCanonical,
            $this->username,
            $this->enabled,
            $this->id,
            $this->email,
            $this->emailCanonical,
        ];
    }

    /**
     * @param mixed[] $data
     */
    public function __unserialize(array $data): void
    {
        [
            $this->password,
            $this->salt,
            $this->usernameCanonical,
            $this->username,
            $this->enabled,
            $this->id,
            $this->email,
            $this->emailCanonical
        ] = $data;
    }

    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    public function unserialize($data): void
    {
        $this->__unserialize(unserialize($data));
    }
}
