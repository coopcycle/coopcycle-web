<?php

namespace AppBundle\Security;

use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Webmozart\Assert\Assert;

class BusinessVoter implements VoterInterface
{
    private static $role = 'ROLE_BUSINESS';
    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $subject, array $attributes)
    {
        $result = self::ACCESS_ABSTAIN;

        foreach ($attributes as $attribute) {

            if (!is_string($attribute)) {
                continue;
            }

            if (self::$role !== $attribute) {
                continue;
            }

            if (!\is_object($user = $token->getUser())) {
                // e.g. anonymous authentication
                return self::ACCESS_DENIED;
            }

            Assert::isInstanceOf($user, User::class);

            if ($user->hasBusinessAccount()) {
                return self::ACCESS_GRANTED;
            }
        }

        return $result;
    }
}
