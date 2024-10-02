<?php

namespace AppBundle\Security;

use AppBundle\Entity\TimeSlot;
use AppBundle\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TimeSlotVoter extends Voter
{
    const EDIT = 'edit';
    const VIEW = 'view';

    private static $actions = [
        self::EDIT,
        self::VIEW,
    ];

    private $authorizationChecker;
    private $storeExtractor;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, TokenStoreExtractor $storeExtractor)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->storeExtractor = $storeExtractor;
    }

    protected function supports($attribute, $subject)
    {
        if (!in_array($attribute, self::$actions)) {
            return false;
        }

        if (!$subject instanceof TimeSlot) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ($this->authorizationChecker->isGranted('ROLE_DISPATCHER')) {
            return true;
        }

        $timeSlots = new ArrayCollection();
        foreach ($this->getStores($token) as $store) {
            foreach ($store->getTimeSlots() as $timeSlot) {
                $timeSlots->add($timeSlot);
            }
        }

        return $timeSlots->contains($subject);
    }

    private function getStores(TokenInterface $token): Collection
    {
        if ($this->authorizationChecker->isGranted('ROLE_OAUTH2_DELIVERIES')
        ||  $this->authorizationChecker->isGranted('ROLE_API_KEY')) {
            return new ArrayCollection([$this->storeExtractor->extractStore()]);
        }

        return $token->getUser()->getStores();
    }
}
