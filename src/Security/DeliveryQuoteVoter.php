<?php

namespace AppBundle\Security;

use AppBundle\Entity\DeliveryQuote;
use AppBundle\Security\TokenStoreExtractor;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class DeliveryQuoteVoter extends Voter
{
    const CONFIRM = 'confirm';

    private $authorizationChecker;
    private $storeExtractor;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, TokenStoreExtractor $storeExtractor)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->storeExtractor = $storeExtractor;
    }

    protected function supports($attribute, $subject)
    {
        if (!$subject instanceof DeliveryQuote) {
            return false;
        }

        return $attribute === self::CONFIRM;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $store = $this->storeExtractor->extractStore();

        return $subject->getStore() === $store;
    }
}
