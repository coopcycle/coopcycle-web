<?php

namespace AppBundle\Security;

use AppBundle\Entity\ImportQueue;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Who may look at an import and act on it.
 *
 * The imports tab lives behind ^/admin, which opens to ROLE_DISPATCHER: the dispatchers
 * who import the files are granted what they can already see, rather than being shown a
 * list whose every button answers 403.
 */
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
        // ROLE_ADMIN includes ROLE_DISPATCHER
        if ($this->authorizationChecker->isGranted('ROLE_DISPATCHER')) {
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

