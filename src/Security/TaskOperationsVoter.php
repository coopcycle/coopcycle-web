<?php

namespace AppBundle\Security;

use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Webmozart\Assert\Assert;

class TaskOperationsVoter extends Voter
{
    const VIEW = 'view';

    private static $actions = [
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

        if (!$subject instanceof Task) {
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

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')
        ||  $this->authorizationChecker->isGranted('ROLE_COURIER')) {
            return true;
        }

        Assert::isInstanceOf($user, User::class);

        if ($this->authorizationChecker->isGranted('ROLE_STORE')) {
            $delivery = $subject->getDelivery();

            if (null !== $delivery) {
                $store = $delivery->getStore();

                if (null !== $store) {

                    return $user->ownsStore($store);
                }
            }
        }

        return false;
    }
}
