<?php

namespace AppBundle\Security;

use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserVoter extends Voter
{

    const EDIT = 'edit';
    const VIEW = 'view';
    const IMPERSONATE = 'impersonate';
    const DELETE = 'delete';
    const ANONYMIZE = 'anonymize';
    const INVITE = 'invite';

    private static $actions = [
        self::EDIT,
        self::VIEW,
        self::IMPERSONATE,
        self::DELETE,
        self::ANONYMIZE,
        self::INVITE,
    ];
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker
    )
    {}
    protected function supports(string $attribute, $subject)
    {
        if (!in_array($attribute, self::$actions)) {
            return false;
        }

        if (!$subject instanceof User) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token)
    {

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return true;
        }
        if (
            in_array($attribute, [self::VIEW], self::INVITE) &&
            $this->authorizationChecker->isGranted('ROLE_DISPATCHER')
        ) {
            return true;
        }

        return false;
    }
}
