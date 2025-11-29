<?php

namespace AppBundle\Security;

use AppBundle\Entity\Store;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class StoreVoter extends Voter
{
    const EDIT = 'edit';
    const VIEW = 'view';
    const EDIT_DELIVERY = 'edit_delivery';

    private static $actions = [
        self::EDIT,
        self::VIEW,
        self::EDIT_DELIVERY
    ];

    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private TokenStoreExtractor $storeExtractor,
        private EntityManagerInterface $entityManager)
    {}

    protected function supports($attribute, $subject): bool
    {
        if (!in_array($attribute, self::$actions)) {
            return false;
        }

        // Needed for /api/stores/{id}/deliveries endpoint
        // Might be removed when upgrading to 4.0
        // https://api-platform.com/docs/v4.0/core/subresources/#security
        if (!$subject instanceof Store && !$subject instanceof Request) {
            return false;
        }

        return true;
    }

    /**
     * @param Store|Request $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        if ($subject instanceof Request) {
            $subject = $this->entityManager->getRepository(Store::class)->find($subject->get('id'));
        }

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ($this->authorizationChecker->isGranted('ROLE_OAUTH2_DELIVERIES')
        ||  $this->authorizationChecker->isGranted('ROLE_API_KEY')) {
            return $subject === $this->storeExtractor->extractStore();
        }

        $user = $token->getUser();

        if ($this->authorizationChecker->isGranted('ROLE_STORE')
            && is_object($user) && is_callable([ $user, 'ownsStore' ]) && $user->ownsStore($subject)) {
            return true;
        }

        if (
            in_array($attribute, [self::VIEW, self::EDIT_DELIVERY]) &&
            $this->authorizationChecker->isGranted('ROLE_DISPATCHER')
        ) {
            return true;
        }

        return false;
    }
}
