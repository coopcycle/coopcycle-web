<?php

namespace AppBundle\Entity;

use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Me as MeController;
use AppBundle\Api\Filter\UserRoleFilter;
use AppBundle\LoopEat\OAuthCredentialsTrait as LoopEatOAuthCredentialsTrait;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Product\ProductInterface;
use FOS\UserBundle\Model\User as BaseUser;
use Gedmo\Timestampable\Traits\Timestampable;
use Doctrine\Common\Collections\ArrayCollection;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;
use Sylius\Component\Channel\Model\ChannelAwareInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;

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
 *     }
 *   },
 *   attributes={
 *     "normalization_context"={ "groups"={"user", "order"} }
 *   }
 * )
 * @ApiFilter(UserRoleFilter::class, properties={"roles"})
 * @UniqueEntity("email")
 * @UniqueEntity(fields={"emailCanonical"}, errorPath="email")
 * @UniqueEntity("username")
 * @UniqueEntity(fields={"usernameCanonical"}, errorPath="username")
 * @UniqueEntity("facebookId")
 */
class ApiUser extends BaseUser implements JWTUserInterface, ChannelAwareInterface
{
    use LoopEatOAuthCredentialsTrait;
    use Timestampable;

    protected $id;

    /**
     * @Assert\NotBlank()
     * @Assert\Length(min="3", max="15")
     * @Assert\Regex(pattern="/^[a-zA-Z0-9_]{3,15}$/")
     * @var string
     */
    protected $username;

    /**
     * @Assert\NotBlank()
     * @var string
     */
    protected $email;

    /**
     * @Assert\NotBlank()
     * @ApiProperty(iri="https://schema.org/givenName")
    */
    protected $givenName;

    /**
     * @Assert\NotBlank()
     * @ApiProperty(iri="https://schema.org/familyName")
     */
    protected $familyName;

    /**
     * @var PhoneNumber|null
     * @AssertPhoneNumber
     * @ApiProperty(iri="https://schema.org/telephone")
     */
    protected $telephone;

    private $restaurants;

    private $stores;

    private $stripeAccounts;

    private $remotePushTokens;

    protected $channel;

    protected $facebookId;

    protected $facebookAccessToken;

    protected $quotesAllowed = false;

    /**
     * @var CustomerInterface|null
     */
    protected $customer;

    private $mercadopagoAccounts;

    public function __construct()
    {
        $this->restaurants = new ArrayCollection();
        $this->stores = new ArrayCollection();
        $this->stripeAccounts = new ArrayCollection();
        $this->remotePushTokens = new ArrayCollection();
        $this->mercadopagoAccounts = new ArrayCollection();

        parent::__construct();
    }

    /**
     * @return mixed
     */
    public function getGivenName()
    {
        return $this->givenName;
    }

    /**
     * @param mixed $givenName
     */
    public function setGivenName($givenName)
    {
        $this->givenName = $givenName;

        if (null !== $this->customer) {
            $this->customer->setFirstName($givenName);
        }
    }

    /**
     * @return mixed
     */
    public function getFamilyName()
    {
        return $this->familyName;
    }

    /**
     * @param mixed $familyName
     */
    public function setFamilyName($familyName)
    {
        $this->familyName = $familyName;

        if (null !== $this->customer) {
            $this->customer->setLastName($familyName);
        }
    }

    /**
     * @return mixed
     */
    public function getTelephone()
    {
        return $this->telephone;
    }

    /**
     * @param PhoneNumber|string $telephone
     */
    public function setTelephone($telephone)
    {
        $this->telephone = $telephone;

        if (null !== $this->customer) {
            if ($telephone instanceof PhoneNumber) {
                $this->customer->setPhoneNumber(
                    PhoneNumberUtil::getInstance()->format($telephone, PhoneNumberFormat::E164)
                );
            } else {
                $this->customer->setPhoneNumber($telephone);
            }
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
        return join(' ', [$this->givenName, $this->familyName]);
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
    public function setEmail($email)
    {
        $value = parent::setEmail($email);

        if (null !== $this->customer) {
            $this->customer->setEmail($email);
        }

        return $value;
    }

    public function ownsProduct(ProductInterface $product)
    {
        foreach ($this->getRestaurants() as $restaurant) {
            if ($restaurant->hasProduct($product)) {

                return true;
            }
        }

        return false;
    }

    public function getMercadopagoAccounts()
    {
        return $this->mercadopagoAccounts;
    }

    public function addMercadopagoAccount(MercadopagoAccount $account)
    {
        $this->mercadopagoAccounts->add($account);

        return $this;
    }
}
