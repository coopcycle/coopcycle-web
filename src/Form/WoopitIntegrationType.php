<?php

namespace AppBundle\Form;

use AppBundle\Entity\Store;
use AppBundle\Entity\Woopit\WoopitIntegration;
use AppBundle\Entity\Zone;
use AppBundle\Form\Type\QueryBuilder\OrderByNameQueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WoopitIntegrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'form.integration.name.label',
            ])
            ->add('maxWeight', NumberType::class, [
                'required' => false,
                'html5' => true,
                'label' => 'form.integration.maxWeight.label',
                'attr'  => array(
                    'min'  => 0,
                    'step' => 0.1,
                ),
            ])
            ->add('maxHeight', NumberType::class, [
                'required' => false,
                'html5' => true,
                'label' => 'form.integration.maxHeight.label',
                'attr'  => array(
                    'min'  => 0,
                    'step' => 0.1,
                ),
            ])
            ->add('maxWidth', NumberType::class, [
                'required' => false,
                'html5' => true,
                'label' => 'form.integration.maxWidth.label',
                'attr'  => array(
                    'min'  => 0,
                    'step' => 0.1,
                ),
            ])
            ->add('maxLength', NumberType::class, [
                'required' => false,
                'html5' => true,
                'label' => 'form.integration.maxLength.label',
                'attr'  => array(
                    'min'  => 0,
                    'step' => 0.1,
                ),
            ])
            ->add('woopitStoreId', TextType::class, [
                'label' => 'form.integration.woopitStoreId.label',
                'required' => true,
            ])
            ->add('store', EntityType::class, [
                'class' => Store::class,
                'query_builder' => new OrderByNameQueryBuilder(),
                'label' => 'form.integration.store.label',
                'choice_label' => 'name',
                'required' => true,
            ])
            ->add('zone', EntityType::class, [
                'class' => Zone::class,
                'query_builder' => new OrderByNameQueryBuilder(),
                'label' => 'form.integration.zone.label',
                'choice_label' => 'name',
                'required' => false,
            ])
            ->add('productTypes', ChoiceType::class, [
                'label' => 'form.integration.productTypes.label',
                'choices' => WoopitIntegration::allProductTypes(),
                'choice_label' => function($type) {
                    return $type;
                },
                'expanded' => true,
                'multiple' => true
            ]);

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $woopitIntegration = $event->getData();

            if (null !== $woopitIntegration->getZone()) {
                $woopitIntegration->getStore()->setCheckExpression(
                    sprintf('in_zone(dropoff.address, "%s")', $woopitIntegration->getZone()->getName())
                );
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => WoopitIntegration::class,
        ));
    }
}
