<?php

namespace AppBundle\Security;

use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\User;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class IncidentVoter extends Voter
{
    const CREATE = 'create';
    const VIEW = 'view';
    const EDIT = 'edit';

    private static $actions = [
        self::CREATE,
        self::VIEW,
        self::EDIT,
    ];

    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private TokenStoreExtractor $storeExtractor)
    {}

    protected function supports($attribute, $subject): bool
    {
        if (!$subject instanceof Incident) {
            return false;
        }

        if (!in_array($attribute, self::$actions)) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        // We allow stores to access their tasks incidents
        if ($token instanceof OAuth2Token) {

            if (!$this->authorizationChecker->isGranted('ROLE_OAUTH2_TASKS')) {
                return false;
            }

            $store = $this->storeExtractor->extractStore();

            if (null === $store || !$subject->hasTask()) {
                return false;
            }

            return $store === $subject->getTask()->getStore();
        }

        // Dispatchers & admins manage the incidents of the whole instance
        if ($this->authorizationChecker->isGranted('ROLE_DISPATCHER')) {
            return true;
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Couriers are only allowed to deal with the incidents of the tasks
        // they are assigned to, or with the ones they reported themselves.
        if ($this->authorizationChecker->isGranted('ROLE_COURIER')) {

            if ($subject->getCreatedBy() === $user) {
                return true;
            }

            return $subject->hasTask() && $subject->getTask()->isAssignedTo($user);
        }

        return false;
    }
}
