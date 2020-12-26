<?php

namespace AppBundle\Security;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Trikoder\Bundle\OAuth2Bundle\Security\Authentication\Token\OAuth2Token;
use Webmozart\Assert\Assert;

class DeliveriesVoter extends Voter
{
    const CREATE = 'create';
    const VIEW   = 'view';
    const EDIT   = 'edit';

    private static $actions = [
        self::CREATE,
        self::VIEW,
        self::EDIT,
    ];

    private $authorizationChecker;
    private $storeExtractor;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, TokenStoreExtractor $storeExtractor)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->storeExtractor = $storeExtractor;
    }

    protected function supports($attribute, $subject)
    {
        if (!in_array($attribute, self::$actions)) {
            return false;
        }

        if (!$subject instanceof Delivery) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ($this->authorizationChecker->isGranted('ROLE_OAUTH2_DELIVERIES')) {

            if (self::CREATE === $attribute && null === $subject->getStore()) {
                return true;
            }

            if ($subject->getStore() === $this->storeExtractor->extractStore()) {
                return true;
            }
        }

        $user = $token->getUser();
        $store = $subject->getStore();

        if ($store && is_object($user) && is_callable([ $user, 'ownsStore' ]) && $user->ownsStore($store)) {
            return true;
        }

        return false;
    }
}
