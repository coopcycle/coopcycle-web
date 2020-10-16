<?php

namespace AppBundle\Security;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Webmozart\Assert\Assert;

class OrderActionsVoter extends Voter
{
    const ACCEPT  = 'accept';
    const REFUSE  = 'refuse';
    const DELAY   = 'delay';
    const FULFILL = 'fulfill';
    const CANCEL  = 'cancel';
    const VIEW    = 'view';

    private static $actions = [
        self::ACCEPT,
        self::REFUSE,
        self::DELAY,
        self::FULFILL,
        self::CANCEL,
        self::VIEW,
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

        if (!$subject instanceof Order) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if (!is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return false;
        }

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return true;
        }

        Assert::isInstanceOf($user, User::class);

        $ownsRestaurant = $user->ownsRestaurant($subject->getRestaurant());

        $isCustomer = null !== $subject->getCustomer()
            && $subject->getCustomer()->hasUser()
            && $subject->getCustomer()->getUser() === $user;

        if (self::VIEW === $attribute) {

            return $ownsRestaurant || $isCustomer;
        }

        // For actions like "accept", "refuse", etc...
        return $this->authorizationChecker->isGranted('ROLE_RESTAURANT') && $ownsRestaurant;
    }
}
