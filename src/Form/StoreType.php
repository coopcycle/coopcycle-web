<?php

namespace AppBundle\Form;

use AppBundle\Entity\Delivery\FailureReasonSet;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\PackageSet;
use AppBundle\Entity\Store;
use AppBundle\Entity\TimeSlot;
use AppBundle\Form\Type\QueryBuilder\OrderByNameQueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints;

class StoreType extends LocalBusinessType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $builder
                ->add('pricingRuleSet', EntityType::class, array(
                    'label' => 'form.store_type.pricing_rule_set.label',
                    'class' => PricingRuleSet::class,
                    'choice_label' => 'name',
                    'query_builder' => new OrderByNameQueryBuilder(),
                ))
                ->add('packageSet', EntityType::class, array(
                    'label' => 'form.store_type.package_set.label',
                    'class' => PackageSet::class,
                    'choice_label' => 'name',
                    'query_builder' => new OrderByNameQueryBuilder(),
                    'required' => false,
                ))
                ->add('prefillPickupAddress', CheckboxType::class, [
                    'label' => 'form.store_type.prefill_pickup_address.label',
                    'required' => false,
                ])
                ->add('createOrders', CheckboxType::class, [
                    'label' => 'form.store_type.create_orders.label',
                    'required' => false,
                ])
                ->add('weightRequired', CheckboxType::class, [
                    'label' => 'form.store_type.weight_required.label',
                    'required' => false,
                ])
                ->add('packagesRequired', CheckboxType::class, [
                    'label' => 'form.store_type.packages_required.label',
                    'required' => false,
                ])
                ->add('multiDropEnabled', CheckboxType::class, [
                    'label' => 'form.store_type.multi_drop_enabled.label',
                    'help' => 'form.store_type.multi_drop_enabled.help',
                    'required' => false,
                ])
                ->add('timeSlot', EntityType::class, [
                    'label' => 'form.store_type.time_slot.label',
                    'class' => TimeSlot::class,
                    'choice_label' => 'name',
                    'required' => false,
                    'query_builder' => new OrderByNameQueryBuilder(),
                ])
                ->add('timeSlots', EntityType::class, [
                    'label' => 'form.store_type.time_slots.label',
                    'class' => TimeSlot::class,
                    'choice_label' => 'name',
                    'required' => false,
                    'expanded' => true,
                    'multiple' => true,
                    'query_builder' => new OrderByNameQueryBuilder(),
                ])
                ->add('tags', TagsType::class)
                ->add('failureReasonSet', EntityType::class, array(
                    'label' => 'form.store_type.failure_reason_set.label',
                    'class' => FailureReasonSet::class,
                    'choice_label' => 'name',
                    'query_builder' => new OrderByNameQueryBuilder(),
                    'required' => false,
                ));
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            $store = $event->getData();

            if (null !== $store && null !== $store->getId()) {
                // Remove default address form
                $form->remove('address');
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $store = $event->getData();

            if (null === $store->getId()) {
                $defaultAddress = $store->getAddress();
                $store->addAddress($defaultAddress);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(array(
            'data_class' => Store::class,
        ));
    }
}
