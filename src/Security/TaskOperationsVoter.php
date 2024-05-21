<?php

namespace AppBundle\Security;

use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Webmozart\Assert\Assert;

class TaskOperationsVoter extends Voter
{
    const VIEW = 'view';

    private static $actions = [
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

        if (!$subject instanceof Task) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ($token instanceof OAuth2Token) {
            return $this->voteOnAttributeWithOAuth($attribute, $subject);
        }

        if ($this->authorizationChecker->isGranted('ROLE_DISPATCHER')) {
            return true;
        }

        if (!is_object($user = $token->getUser())) {
            return false;
        }

        if ($this->authorizationChecker->isGranted('ROLE_COURIER') && $subject->isAssignedTo($user)) {
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

    private function voteOnAttributeWithOAuth($attribute, $subject)
    {
        if (!$this->authorizationChecker->isGranted('ROLE_OAUTH2_TASKS')) {
            return false;
        }

        $store = $this->storeExtractor->extractStore();

        if (null === $store) {
            return false;
        }

        return $subject->getOrganization() === $store->getOrganization();
    }
}
