<?php

namespace AppBundle\Security;

use AppBundle\Sylius\Product\ProductInterface;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Webmozart\Assert\Assert;

class ProductVoter extends Voter
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

        if (!$subject instanceof ProductInterface) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if (!is_object($user = $token->getUser())) {
            return false;
        }

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ($this->authorizationChecker->isGranted('ROLE_RESTAURANT')) {

            Assert::isInstanceOf($user, User::class);

            foreach ($user->getRestaurants() as $restaurant) {
                if ($restaurant->hasProduct($subject)) {

                    return true;
                }
            }
        }

        return false;
    }
}
