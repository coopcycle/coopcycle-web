<?php

namespace AppBundle\Sylius\Promotion\Checker\Rule;

use AppBundle\Entity\LocalBusiness;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Promotion\Checker\Rule\RuleCheckerInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;

class IsRestaurantRuleChecker implements RuleCheckerInterface
{
    const TYPE = 'is_restaurant';

    private $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * {@inheritdoc}
     */
    public function isEligible(PromotionSubjectInterface $subject, array $configuration): bool
    {
        if (!isset($configuration['restaurant_id']) || empty($configuration['restaurant_id'])) {
            return false;
        }

        if (null === $subject->getRestaurant()) {
            return false;
        }

        $restaurant = $this->doctrine->getRepository(LocalBusiness::class)->find($configuration['restaurant_id']);

        if (null === $restaurant) {
            return false;
        }

        return $subject->getRestaurant() === $restaurant;
    }
}
