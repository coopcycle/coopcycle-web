<?php

namespace AppBundle\Twig;

use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Sylius\Promotion\Action;
use AppBundle\Sylius\Promotion\Checker\Rule;
use AppBundle\Utils\PriceFormatter;
use Sylius\Component\Promotion\Model\PromotionActionInterface;
use Sylius\Component\Promotion\Model\PromotionRuleInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\RuntimeExtensionInterface;

class PromotionRuntime implements RuntimeExtensionInterface
{
    private $translator;
    private $repository;
    private $priceFormatter;

    public function __construct(TranslatorInterface $translator, LocalBusinessRepository $repository, PriceFormatter $priceFormatter)
    {
        $this->translator = $translator;
        $this->repository = $repository;
        $this->priceFormatter = $priceFormatter;
    }

    public function ruleForHumans(PromotionRuleInterface $rule)
    {
        $configuration = $rule->getConfiguration();

        switch ($rule->getType()) {
            case Rule\IsCustomerRuleChecker::TYPE:
                return $this->translator->trans('promotion_rule.is_customer', ['%username%' => $configuration['username']]);
            case Rule\IsRestaurantRuleChecker::TYPE:
                $restaurant = $this->repository->find($configuration['restaurant_id']);
                return $this->translator->trans('promotion_rule.is_restaurant', ['%restaurant%' => $restaurant->getName()]);
            case Rule\IsItemsTotalAboveRuleChecker::TYPE:
                return $this->translator->trans('promotion_rule.is_items_total_above', [
                    '%amount%' => $this->priceFormatter->formatWithSymbol($configuration['amount'])
                ]);
        }

        return $rule->getType();
    }

    public function actionForHumans(PromotionActionInterface $action)
    {
        $configuration = $action->getConfiguration();

        $percentFormatter = new \NumberFormatter(\Locale::getDefault(), \NumberFormatter::PERCENT);

        switch ($action->getType()) {
            case Action\DeliveryPercentageDiscountPromotionActionCommand::TYPE:
                return $this->translator->trans('promotion_action.delivery_percentage_discount', [
                    '%percentage%' => $percentFormatter->format($configuration['percentage'])
                ]);
            case Action\PercentageDiscountPromotionActionCommand::TYPE:
                return $this->translator->trans('promotion_action.percentage_discount', [
                    '%percentage%' => $percentFormatter->format($configuration['percentage'])
                ]);
            case Action\FixedDiscountPromotionActionCommand::TYPE:
                return $this->translator->trans('promotion_action.fixed_discount', [
                    '%amount%' => $this->priceFormatter->formatWithSymbol($configuration['amount'])
                ]);
        }

        return $action->getType();
    }
}
