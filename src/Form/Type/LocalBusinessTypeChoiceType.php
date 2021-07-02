<?php

namespace AppBundle\Form\Type;

use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LocalBusinessTypeChoiceType extends AbstractType
{
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        // @see https://stackoverflow.com/questions/21553719/symfony2-sort-order-a-translated-entity-form-field

        $collator = new \Collator($this->translator->getLocale());

        usort(
            $view->vars['choices'],
            function ($a, $b) use ($collator) {
                return $collator->compare(
                    $this->translator->trans($a->label),
                    $this->translator->trans($b->label)
                );
            }
        );
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

        $resolver->setDefaults([
            'choices' => $choices,
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
