<?php

namespace AppBundle\Twig;

use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Sylius\Promotion\Checker\Rule;
use Sylius\Component\Promotion\Model\PromotionRuleInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\RuntimeExtensionInterface;

class PromotionRuntime implements RuntimeExtensionInterface
{
    private $translator;
    private $repository;

    public function __construct(TranslatorInterface $translator, LocalBusinessRepository $repository)
    {
        $this->translator = $translator;
        $this->repository = $repository;
    }

    public function ruleForHumans(PromotionRuleInterface $rule)
    {
        $configuration = $rule->getConfiguration();

        if ($rule->getType() === Rule\IsCustomerRuleChecker::TYPE) {

            return $this->translator->trans('promotion_rule.is_customer', ['%username%' => $configuration['username']]);
        }

        if ($rule->getType() === Rule\IsRestaurantRuleChecker::TYPE) {

            $restaurant = $this->repository->find($configuration['restaurant_id']);

            return $this->translator->trans('promotion_rule.is_restaurant', ['%restaurant%' => $restaurant->getName()]);
        }

        return '';
    }
}
