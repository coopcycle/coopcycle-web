<?php

namespace AppBundle\Security;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Webmozart\Assert\Assert;

class LocalBusinessVoter extends Voter
{
    const VIEW = 'view';
    const EDIT = 'edit';

    private static $actions = [
        self::VIEW,
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

        if (!$subject instanceof LocalBusiness) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if (self::VIEW === $attribute && $subject->isEnabled()) {
            return true;
        }

        if (!is_object($user = $token->getUser())) {
            return false;
        }

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return true;
        }

        Assert::isInstanceOf($user, User::class);

        if ($this->authorizationChecker->isGranted('ROLE_RESTAURANT')) {
            return $user->ownsRestaurant($subject);
        }

        return false;
    }
}
