<?php

namespace AppBundle\Security;

use AppBundle\Entity\ImportQueue;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ImportQueueVoter extends Voter
{
    public function __construct(private AuthorizationCheckerInterface $authorizationChecker)
    {
    }

    protected function supports($attribute, $subject): bool
    {
        return $subject instanceof ImportQueue;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $user = $token->getUser();

        // An import with no store, a task import for instance, belongs to the co-op
        // rather than to one of its stores: there is nothing here for a store user.
        $store = $subject->getStore();

        if (!is_null($store)
            && $this->authorizationChecker->isGranted('ROLE_STORE')
            && is_object($user) && is_callable([ $user, 'ownsStore' ]) && $user->ownsStore($store)) {
            return true;
        }

        return false;
    }
}

