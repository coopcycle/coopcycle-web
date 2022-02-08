<?php

namespace AppBundle\Security;

use AppBundle\Entity\Webhook;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use League\Bundle\OAuth2ServerBundle\Manager\AccessTokenManagerInterface;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Webmozart\Assert\Assert;

class WebhookVoter extends Voter
{
    const CREATE = 'create';
    const VIEW   = 'view';
    const EDIT   = 'edit';

    private static $actions = [
        self::CREATE,
        self::VIEW,
        self::EDIT,
    ];

    private $accessTokenManager;

    public function __construct(
        AccessTokenManagerInterface $accessTokenManager)
    {
        $this->accessTokenManager = $accessTokenManager;
    }

    protected function supports($attribute, $subject)
    {
        if (!in_array($attribute, self::$actions)) {
            return false;
        }

        if (!$subject instanceof Webhook) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if (!$token instanceof OAuth2Token) {
            return false;
        }

        if (self::CREATE === $attribute) {
            return true;
        }

        $accessToken = $this->accessTokenManager->find($token->getCredentials());

        return $subject->getOauth2Client() === $accessToken->getClient();
    }
}
