<?php

namespace AppBundle\Security;

use AppBundle\Entity\Store;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class StoreVoter extends Voter
{
    const EDIT = 'edit';

    private static $actions = [
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

        if (!$subject instanceof Store) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ($this->authorizationChecker->isGranted('ROLE_OAUTH2_DELIVERIES')
        ||  $this->authorizationChecker->isGranted('ROLE_API_KEY')) {
            return $subject === $this->storeExtractor->extractStore();
        }

        $user = $token->getUser();

        if ($this->authorizationChecker->isGranted('ROLE_STORE')
            && is_object($user) && is_callable([ $user, 'ownsStore' ]) && $user->ownsStore($subject)) {
            return true;
        }

        return false;
    }
}
