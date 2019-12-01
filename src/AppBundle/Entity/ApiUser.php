<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Me as MeController;
use AppBundle\Api\Filter\UserRoleFilter;
use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\Common\Collections\ArrayCollection;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;
use Sylius\Component\Channel\Model\ChannelAwareInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
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
 *     "me"={
 *       "method"="GET",
 *       "path"="/me",
 *       "controller"=MeController::class,
 *       "read"=false,
 *       "normalization_context"={"groups"={"user", "place"}}
 *     }
 *   },
 *   collectionOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_ADMIN')"
 *     }
 *   },
 *   attributes={
 *     "normalization_context"={ "groups"={"user", "order"} }
 *   }
 * )
 * @ApiFilter(UserRoleFilter::class, properties={"roles"})
 * @UniqueEntity("email")
 * @UniqueEntity("username")
 * @UniqueEntity("facebookId")
 */
class ApiUser extends BaseUser implements JWTUserInterface, ChannelAwareInterface
{
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
     * @Groups({"order"})
     * @Assert\NotBlank()
     * @ApiProperty(iri="https://schema.org/givenName")
    */
    protected $givenName;

    /**
     * @Groups({"order"})
     * @Assert\NotBlank()
     * @ApiProperty(iri="https://schema.org/familyName")
     */
    protected $familyName;

    /**
     * @Groups({"order"})
     * @Assert\NotBlank()
     * @AssertPhoneNumber
     * @ApiProperty(iri="https://schema.org/telephone")
     */
    protected $telephone;

    private $restaurants;

    private $stores;

    private $addresses;

    private $stripeAccounts;

    private $remotePushTokens;

    protected $channel;

    protected $facebookId;

    protected $facebookAccessToken;

    protected $quotesAllowed = false;

    public function __construct()
    {
        $this->addresses = new ArrayCollection();
        $this->restaurants = new ArrayCollection();
        $this->stores = new ArrayCollection();
        $this->stripeAccounts = new ArrayCollection();
        $this->remotePushTokens = new ArrayCollection();

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
    }

    /**
     * @return mixed
     */
    public function getTelephone()
    {
        return $this->telephone;
    }

    /**
     * @param mixed $telephone
     */
    public function setTelephone($telephone)
    {
        $this->telephone = $telephone;
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

    public function addRestaurant(Restaurant $restaurant)
    {
        $this->restaurants->add($restaurant);

        return $this;
    }

    public function ownsRestaurant(Restaurant $restaurant)
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

    public function addAddress(Address $addresses)
    {
        $this->addresses->add($addresses);

        return $this;
    }

    public function setAddresses($addresses)
    {
        $this->addresses = $addresses;

        return $this;
    }

    public function getAddresses()
    {
        return $this->addresses;
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
}
