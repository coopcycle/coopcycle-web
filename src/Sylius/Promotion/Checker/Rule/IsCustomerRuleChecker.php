<?php

namespace AppBundle\Sylius\Promotion\Checker\Rule;

use Symfony\Component\Security\Core\Security;
use Sylius\Component\Promotion\Checker\Rule\RuleCheckerInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;

class IsCustomerRuleChecker implements RuleCheckerInterface
{
    const TYPE = 'is_customer';

    public function __construct(private Security $security)
    {}

    /**
     * {@inheritdoc}
     */
    public function isEligible(PromotionSubjectInterface $subject, array $configuration): bool
    {
        if (!isset($configuration['username']) || empty($configuration['username'])) {
            return true;
        }

        $user = $this->security->getUser();

        if (!$user) {
            return false;
        }

        return $user->getUserIdentifier() === $configuration['username'];
    }
}
