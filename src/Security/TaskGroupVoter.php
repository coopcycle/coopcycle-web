<?php

namespace AppBundle\Security;

use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TaskGroupVoter extends Voter
{
    const VIEW = 'view';
    const EDIT = 'edit';
    const CREATE = 'create';

    private static $actions = [
        self::VIEW,
        self::EDIT,
        self::CREATE,
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

        if (!$subject instanceof TaskGroup) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if (self::EDIT === $attribute || self::CREATE === $attribute) {
            return false;
        }

        if (!$this->authorizationChecker->isGranted('ROLE_OAUTH2_TASKS')) {
            return false;
        }

        $store = $this->storeExtractor->extractStore();

        foreach ($subject->getTasks() as $task) {

            $organization = $task->getOrganization();

            if ($organization === null) {
                return false;
            }

            if ($organization !== $store->getOrganization()) {
                return false;
            }
        }

        return true;
    }
}
