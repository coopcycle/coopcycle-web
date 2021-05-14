<?php

namespace AppBundle\Security;

use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Webmozart\Assert\Assert;

class DashboardViewVoter implements VoterInterface
{
    const DASHBOARD_VIEW = 'DASHBOARD_VIEW';

    private $authorizationChecker;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $subject, array $attributes)
    {
        $result = self::ACCESS_ABSTAIN;

        foreach ($attributes as $attribute) {

            if (null === $attribute || self::DASHBOARD_VIEW !== $attribute) {
                continue;
            }

            if (!is_object($user = $token->getUser())) {
                // e.g. anonymous authentication
                return VoterInterface::ACCESS_DENIED;
            }

            Assert::isInstanceOf($user, User::class);

            if ($this->authorizationChecker->isGranted('ROLE_STORE') && count($user->getStores()) > 0) {
                return VoterInterface::ACCESS_GRANTED;
            }

            if ($this->authorizationChecker->isGranted('ROLE_RESTAURANT') && count($user->getRestaurants()) > 0) {
                return VoterInterface::ACCESS_GRANTED;
            }

            return VoterInterface::ACCESS_DENIED;
        }

        return $result;
    }
}
