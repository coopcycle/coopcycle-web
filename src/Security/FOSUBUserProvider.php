<?php

namespace AppBundle\Security;

use Cocur\Slugify\SlugifyInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\FOSUBUserProvider as BaseProvider;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @see https://github.com/hwi/HWIOAuthBundle/blob/master/Resources/doc/4-integrating_fosub.md
 */
class FOSUBUserProvider extends BaseProvider
{
    public function __construct(
        UserManagerInterface $userManager,
        array $properties,
        SlugifyInterface $slugify)
    {
        parent::__construct($userManager, $properties);

        $this->slugify = $slugify;
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

            // Also try to match by email
            $user = $this->userManager->findUserByEmail($response->getEmail());
            if (null === $user) {
                $user = $this->createUserFromNickname($response->getNickname());
                $user->setEmail($response->getEmail());
                $user->setPassword(base64_encode(random_bytes(32)));
                $user->setEnabled(true);
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
        $user = parent::loadUserByOAuthUserResponse($response);

        $serviceName = $response->getResourceOwner()->getName();

        $setter = 'set' . ucfirst($serviceName) . 'AccessToken';
        $user->$setter($response->getAccessToken());

        return $user;
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
