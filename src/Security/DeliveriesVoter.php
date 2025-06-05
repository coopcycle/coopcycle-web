<?php

namespace AppBundle\Security;

use AppBundle\Api\Dto\DeliveryDto;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Webmozart\Assert\Assert;

class DeliveriesVoter extends Voter
{
    const CREATE = 'create';
    const VIEW   = 'view';
    const EDIT   = 'edit';

    private static $actions = [
        self::CREATE,
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

        if (!$subject instanceof Delivery && !$subject instanceof DeliveryDto) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ($this->authorizationChecker->isGranted('ROLE_DISPATCHER')) {
            return true;
        }

        $store = $this->getStore($subject);

        if ($this->authorizationChecker->isGranted('ROLE_OAUTH2_DELIVERIES')) {

            if (self::CREATE === $attribute && null === $store) {
                return true;
            }
        }

        return $this->authorizationChecker->isGranted('edit', $store);
    }

    private function getStore($subject)
    {
        if ($subject instanceof DeliveryDto) {
            return $subject->store;
        }

        return $subject->getStore();
    }
}
