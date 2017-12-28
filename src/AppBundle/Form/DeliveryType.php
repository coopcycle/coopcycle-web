<?php

namespace AppBundle\Form;

use AppBundle\Entity\Delivery\PricingRuleSet;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints;
use AppBundle\Entity\Delivery;

class DeliveryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('originAddress', AddressType::class)
            ->add('deliveryAddress', AddressType::class)
            ->add('weight', NumberType::class, ['required' => false])

            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd HH:mm:ss'
            ])
            ->add('price', MoneyType::class)
            ->add('distance', NumberType::class, ['mapped' => false])
            ->add('duration', NumberType::class, ['mapped' => false]);

        if (!empty($options['vehicle_choices'])) {
            $builder->add('vehicle', ChoiceType::class, [
                'required' => false,
                'choices'  => $options['vehicle_choices'],
                'placeholder' => 'form.delivery.vehicle.placeholder'
            ]);
        }

        if (true === $options['free_pricing']) {
            $builder
                ->add('pricingRuleSet', EntityType::class, array(
                    'mapped' => false,
                    'required' => false,
                    'placeholder' => 'form.store_type.pricing_rule_set.placeholder',
                    'label' => 'form.store_type.pricing_rule_set.label',
                    'class' => PricingRuleSet::class,
                    'choice_label' => 'name',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('prs')->orderBy('prs.name', 'ASC');
                    }
                ));
        } else {
            $builder
                ->add('pricingRuleSet', HiddenType::class, array(
                    'mapped' => false,
                ));
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {
            if (false === $options['free_pricing'] && null !== $options['pricing_rule_set']) {
                $event->getForm()->get('pricingRuleSet')->setData($options['pricing_rule_set']->getId());
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Delivery::class,
            'free_pricing' => true,
            'pricing_rule_set' => null,
            'vehicle_choices' => []
        ));
    }
}
