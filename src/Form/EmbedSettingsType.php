<?php

namespace AppBundle\Form;

use AppBundle\Entity\DeliveryForm;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\PackageSet;
use AppBundle\Entity\TimeSlot;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints;

class EmbedSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('pricingRuleSet', EntityType::class, array(
                'required' => true,
                'placeholder' => 'form.store_type.pricing_rule_set.placeholder',
                'label' => 'form.store_type.pricing_rule_set.label',
                'class' => PricingRuleSet::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('prs')->orderBy('prs.name', 'ASC');
                }
            ))
            ->add('timeSlot', EntityType::class, array(
                'required' => false,
                'placeholder' => 'form.store_type.time_slot.placeholder',
                'label' => 'form.store_type.time_slot.label',
                'class' => TimeSlot::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('ts')->orderBy('ts.name', 'ASC');
                }
            ))
            ->add('packageSet', EntityType::class, array(
                'required' => false,
                'placeholder' => 'form.store_type.package_set.placeholder',
                'label' => 'form.store_type.package_set.label',
                'class' => PackageSet::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('ps')->orderBy('ps.name', 'ASC');
                }
            ))
            ->add('withVehicle', CheckboxType::class, [
                'label' => 'form.embed_settings.with_vehicle.label',
                'required' => false
            ])
            ->add('withWeight', CheckboxType::class, [
                'label' => 'form.embed_settings.with_weight.label',
                'required' => false
            ])
            ->add('showHomePage', CheckboxType::class, [
                'label' => 'form.embed_settings.show_home_page.label',
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => DeliveryForm::class,
        ));
    }
}
