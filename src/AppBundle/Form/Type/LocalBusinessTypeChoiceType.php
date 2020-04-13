<?php

namespace AppBundle\Form\Type;

use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LocalBusinessTypeChoiceType extends AbstractType
{
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $choices = [];

        $foodEstablishmentValues = FoodEstablishment::values();

        foreach (FoodEstablishment::values() as $value) {
            $key = sprintf('food_establishment.%s', $value->getKey());
            $choices[$key] = $value->getValue();
        }

        foreach (Store::values() as $value) {
            $key = sprintf('store.%s', $value->getKey());
            $choices[$key] = $value->getValue();
        }

        asort($choices);

        $resolver->setDefaults([
            'choices' => $choices,
            'group_by' => function($choice, $key, $value) {
                if ($found = Store::search($value)) {
                    return $this->translator->trans('form.local_business_type.store');
                }

                return $this->translator->trans('form.local_business_type.food_establishment');
            },
            'label' => 'form.local_business_type.label',
            'help' => 'form.local_business_type.help',
            'help_html' => true,
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}
