<?php

declare(strict_types=1);

namespace Tests\AppBundle\Sylius\OrderProcessing;

use AppBundle\Sylius\Promotion\Action\DeliveryPercentageDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Action\PercentageDiscountPromotionActionCommand;
use Sylius\Component\Promotion\Action\PromotionActionCommandInterface;
use Sylius\Component\Promotion\Action\PromotionApplicator;
use Sylius\Component\Promotion\Checker\Eligibility\PromotionEligibilityCheckerInterface;
use Sylius\Component\Promotion\Model\Promotion;
use Sylius\Component\Promotion\Model\PromotionAction;
use Sylius\Component\Promotion\Model\PromotionInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;
use Sylius\Component\Promotion\Processor\PromotionProcessor;
use Sylius\Component\Promotion\Processor\PromotionProcessorInterface;
use Sylius\Component\Promotion\Provider\PreQualifiedPromotionsProviderInterface;
use Sylius\Component\Registry\ServiceRegistry;

/**
 * Expects $this->adjustmentFactory to be set
 */
trait PromotionProcessorTrait
{
    /**
     * Returns a "real" promotion processor, applying all the given promotions
     */
    private function createPromotionProcessor(array $promotions): PromotionProcessorInterface
    {
        $actions = new ServiceRegistry(PromotionActionCommandInterface::class, 'promotion action');
        $actions->register(DeliveryPercentageDiscountPromotionActionCommand::TYPE,
            new DeliveryPercentageDiscountPromotionActionCommand($this->adjustmentFactory));
        $actions->register(PercentageDiscountPromotionActionCommand::TYPE,
            new PercentageDiscountPromotionActionCommand($this->adjustmentFactory));

        $preQualifiedPromotionsProvider = new class($promotions) implements PreQualifiedPromotionsProviderInterface {
            public function __construct(private array $promotions) {}
            public function getPromotions(PromotionSubjectInterface $subject): array
            {
                return $this->promotions;
            }
        };

        $eligibilityChecker = new class implements PromotionEligibilityCheckerInterface {
            public function isEligible(PromotionSubjectInterface $promotionSubject, PromotionInterface $promotion): bool
            {
                return true;
            }
        };

        return new PromotionProcessor(
            $preQualifiedPromotionsProvider,
            $eligibilityChecker,
            new PromotionApplicator($actions)
        );
    }

    private static function createPromotion(string $code, string $type, array $configuration): Promotion
    {
        $promotion = new Promotion();
        $promotion->setCode($code);
        $promotion->setName($code);

        $promotionAction = new PromotionAction();
        $promotionAction->setType($type);
        $promotionAction->setConfiguration($configuration);

        $promotion->addAction($promotionAction);

        return $promotion;
    }
}
