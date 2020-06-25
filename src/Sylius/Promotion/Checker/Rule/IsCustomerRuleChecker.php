<?php

namespace AppBundle\Sylius\Promotion\Checker\Rule;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Sylius\Component\Promotion\Checker\Rule\RuleCheckerInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;

class IsCustomerRuleChecker implements RuleCheckerInterface
{
    const TYPE = 'is_customer';

    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
    	$this->tokenStorage = $tokenStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function isEligible(PromotionSubjectInterface $subject, array $configuration): bool
    {
        if (!isset($configuration['username']) || empty($configuration['username'])) {
            return true;
        }

    	if (null === $token = $this->tokenStorage->getToken()) {
            return false;
        }

        if (!is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return false;
        }

        return $user->getUsername() === $configuration['username'];
    }
}
