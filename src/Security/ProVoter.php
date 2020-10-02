<?php

namespace AppBundle\Security;

use AppBundle\Entity\User;
use AppBundle\Entity\OrganizationConfig;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Webmozart\Assert\Assert;

class ProVoter implements VoterInterface
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

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

            if ('ROLE_PRO' !== $attribute) {
                continue;
            }

            if (!\is_object($user = $token->getUser())) {
                // e.g. anonymous authentication
                return self::ACCESS_DENIED;
            }

            Assert::isInstanceOf($user, User::class);

            $customer = $user->getCustomer();

            if (null === $customer) {
                return self::ACCESS_DENIED;
            }

            $group = $customer->getGroup();

            if (null === $group) {
                return self::ACCESS_DENIED;
            }

            $orgConfig = $this->entityManager->getRepository(OrganizationConfig::class)
                ->findOneBy(['group' => $group]);

            if (null === $orgConfig) {
                return self::ACCESS_DENIED;
            }

            return self::ACCESS_GRANTED;
        }

        return $result;
    }
}
