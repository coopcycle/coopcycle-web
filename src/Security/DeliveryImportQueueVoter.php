<?php

namespace AppBundle\Security;

use AppBundle\Entity\Delivery\ImportQueue;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class DeliveryImportQueueVoter extends Voter
{
    public function __construct(private AuthorizationCheckerInterface $authorizationChecker)
    {
    }

    protected function supports($attribute, $subject)
    {
        return $subject instanceof ImportQueue;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $user = $token->getUser();

        if ($this->authorizationChecker->isGranted('ROLE_STORE')
            && is_object($user) && is_callable([ $user, 'ownsStore' ]) && $user->ownsStore($subject)) {
            return true;
        }

        return false;
    }
}

