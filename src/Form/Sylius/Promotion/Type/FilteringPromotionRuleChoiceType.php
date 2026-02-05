<?php

namespace AppBundle\Form\Sylius\Promotion\Type;

use AppBundle\Sylius\Promotion\Checker\Rule as PromotionRule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FilteringPromotionRuleChoiceType extends AbstractType
{
    public function __construct(
        private $decorated,
        private array $rules,
        private array $filteredRules)
    {}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $rules = array_filter($this->rules, function ($key) {

            return !in_array($key, $this->filteredRules);

        }, ARRAY_FILTER_USE_KEY);

        $resolver->setDefaults([
            'choices' => array_flip($rules),
        ]);
    }

    public function getParent(): string
    {
        return $this->decorated->getParent();
    }

    public function getBlockPrefix(): string
    {
        return $this->decorated->getBlockPrefix();
    }
}

