<?php

namespace AppBundle\Security;

use AppBundle\Entity\Address;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AddressVoter extends Voter
{
    const EDIT = 'edit';

    private static $actions = [
        self::EDIT,
    ];

    private $authorizationChecker;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    protected function supports($attribute, $subject)
    {
        if (!in_array($attribute, self::$actions)) {
            return false;
        }

        if (!$subject instanceof Address) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return true;
        }

        /** @var User */
        $user = $token->getUser();

        if ($this->authorizationChecker->isGranted('ROLE_STORE') && $user->ownsAddress($subject)) {
            return true;
        }

        return false;
    }
}
