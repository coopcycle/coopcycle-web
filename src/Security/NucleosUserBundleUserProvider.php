<?php

namespace AppBundle\Security;

use Cocur\Slugify\SlugifyInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Nucleos\UserBundle\Model\User;
use Nucleos\UserBundle\Util\Canonicalizer as CanonicalizerInterface;
use HWI\Bundle\OAuthBundle\Connect\AccountConnectorInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @see https://github.com/hwi/HWIOAuthBundle/blob/master/Resources/doc/3-configuring_the_security_layer.md
 * @see https://github.com/hwi/HWIOAuthBundle/blob/master/Resources/doc/4-integrating_fosub.md
 */
class NucleosUserBundleUserProvider implements UserProviderInterface, AccountConnectorInterface, OAuthAwareUserProviderInterface
{
    private $slugify;
    private $canonicalizer;
    private $customerRepository;

    /**
     * @var array
     */
    protected $properties = [
        'identifier' => 'id',
    ];

    public function __construct(
        UserManagerInterface $userManager,
        array $properties,
        SlugifyInterface $slugify,
        CanonicalizerInterface $canonicalizer,
        RepositoryInterface $customerRepository)
    {
        $this->userManager = $userManager;
        $this->properties = array_merge($this->properties, $properties);
        $this->accessor = PropertyAccess::createPropertyAccessor();

        $this->slugify = $slugify;
        $this->canonicalizer = $canonicalizer;
        $this->customerRepository = $customerRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername($username)
    {
        return $this->userManager->findUserByUsername($username);
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        $property = $this->getProperty($response);
        $username = $response->getUsername();

        $user = $this->userManager->findUserBy(array($property => $username));
        if (null === $user) {

            $email = $response->getEmail();

            if (empty($email)) {
                // FIXME
                // This should be checked sooner, because the only way
                // to get the email is to disconnect the app on Facebook,
                // and re-authorize it
                throw new AuthenticationException();
            }

            $emailCanonical = $this->canonicalizer->canonicalize($email);

            // Also try to match by email
            $user = $this->userManager->findUserBy(['emailCanonical' => $emailCanonical]);
            if (null === $user) {
                $user = $this->createUserFromNickname($response->getNickname());
                $user->setEmail($emailCanonical);
                $user->setPassword(base64_encode(random_bytes(32)));
                $user->setEnabled(true);

                // The customer may have ordered previously as a guest
                $customer = $this->customerRepository->findOneBy(['emailCanonical' => $emailCanonical]);
                if (null !== $customer) {
                    $user->setCustomer($customer);
                }
            }

            $service = $response->getResourceOwner()->getName();
            $setter = 'set'.ucfirst($service);
            $setter_id = $setter.'Id';
            $setter_token = $setter.'AccessToken';

            $user->$setter_id($username);
            $user->$setter_token($response->getAccessToken());

            $this->userManager->updateUser($user);

            return $user;
        }

        // User exists, log in using default HWIO method
        // $user = parent::loadUserByOAuthUserResponse($response);

        $serviceName = $response->getResourceOwner()->getName();

        $setter = 'set' . ucfirst($serviceName) . 'AccessToken';
        $user->$setter($response->getAccessToken());

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function connect(UserInterface $user, UserResponseInterface $response)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Expected an instance of "%s", but got "%s".', User::class, \get_class($user)));
        }

        $property = $this->getProperty($response);
        $username = $response->getUsername();

        if (null !== $previousUser = $this->userManager->findUserBy([$property => $username])) {
            $this->disconnect($previousUser, $response);
        }

        if ($this->accessor->isWritable($user, $property)) {
            $this->accessor->setValue($user, $property, $username);
        } else {
            throw new \RuntimeException(sprintf('Could not determine access type for property "%s".', $property));
        }

        $this->userManager->updateUser($user);
    }

    /**
     * Disconnects a user.
     *
     * @param UserInterface         $user
     * @param UserResponseInterface $response
     */
    public function disconnect(UserInterface $user, UserResponseInterface $response)
    {
        $property = $this->getProperty($response);

        $this->accessor->setValue($user, $property, null);
        $this->userManager->updateUser($user);
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user)
    {
        $identifier = $this->properties['identifier'];
        if (!$user instanceof User || !$this->accessor->isReadable($user, $identifier)) {
            throw new UnsupportedUserException(sprintf('Expected an instance of %s, but got "%s".', User::class, \get_class($user)));
        }

        $userId = $this->accessor->getValue($user, $identifier);
        $username = $user->getUsername();

        if (null === $user = $this->userManager->findUserBy([$identifier => $userId])) {
            $exception = new UserNotFoundException(sprintf('User with ID "%d" could not be reloaded.', $userId));
            $exception->setUserIdentifier($userId);

            throw $exception;
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        $userClass = $this->userManager->getClass();

        return $userClass === $class || is_subclass_of($class, $userClass);
    }

    /**
     * Gets the property for the response.
     *
     * @param UserResponseInterface $response
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    protected function getProperty(UserResponseInterface $response)
    {
        $resourceOwnerName = $response->getResourceOwner()->getName();

        if (!isset($this->properties[$resourceOwnerName])) {
            throw new \RuntimeException(sprintf("No property defined for entity for resource owner '%s'.", $resourceOwnerName));
        }

        return $this->properties[$resourceOwnerName];
    }

    private function createUserFromNickname($nickname, $index = 0)
    {
        $username = $this->slugify->slugify($nickname, ['separator' => '_']);

        if ($index > 0) {
            $username = sprintf('%s_%d', $username, $index);
        }

        $user = $this->userManager->findUserByUsername($username);
        if (null !== $user) {

            return $this->createUserFromNickname($nickname, ++$index);
        }

        $user = $this->userManager->createUser();
        $user->setUsername($username);

        return $user;
    }
}
