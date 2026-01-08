<?php

namespace AppBundle\Security;

use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\User;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Webmozart\Assert\Assert;

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
        if ($this->authorizationChecker->isGranted('ROLE_COURIER')) {
            return true;
        }

        // We allow stores to access their tasks incidents
        if ($token instanceof OAuth2Token) {

            if (!$this->authorizationChecker->isGranted('ROLE_OAUTH2_TASKS')) {
                return false;
            }

            $store = $this->storeExtractor->extractStore();

            return $store === $subject->getTask()->getStore();
        }

        return false;
    }
}
