<?php

namespace AppBundle\Form\Restaurant;

use AppBundle\Entity\LocalBusiness\FulfillmentMethod;
use AppBundle\Form\Type\MoneyType;
use Carbon\CarbonInterval;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class FulfillmentMethodType extends AbstractType
{
    private $authorizationChecker;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('openingHours', CollectionType::class, [
                'entry_type' => HiddenType::class,
                'entry_options' => [
                    'error_bubbling' => false
                ],
                'required' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'label' => 'localBusiness.form.openingHours',
                'error_bubbling' => false
            ])
            ->add('openingHoursBehavior', ChoiceType::class, [
                'label' => 'localBusiness.form.openingHoursBehavior',
                'choices'  => [
                    'localBusiness.form.openingHoursBehavior.asap' => 'asap',
                    'localBusiness.form.openingHoursBehavior.time_slot' => 'time_slot',
                ],
                'expanded' => true,
                'multiple' => false,
            ])
            ;

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $builder->add('allowEdit', CheckboxType::class, [
                'label' => 'basics.allow_edit',
                'required' => false,
                'mapped' => false,
            ]);
            $builder->add('rangeDuration', ChoiceType::class, array(
                'label' => 'form.fulfillment_method.options.range_duration.label',
                'mapped' => false,
                'choices' => [
                    '10 minutes' => 10,
                    '20 minutes' => 20,
                    '30 minutes' => 30,
                    '60 minutes' => 60,
                ],
                'expanded' => true,
                'multiple' => false,
            ));
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $fulfillmentMethod = $event->getData();

            $allowEdit =
                ($fulfillmentMethod->hasOption('allow_edit') && true === $fulfillmentMethod->getOption('allow_edit'));

            if ($form->has('allowEdit')) {
                $form->get('allowEdit')->setData($allowEdit);
            }

            if ($form->has('rangeDuration')) {
                $form->get('rangeDuration')->setData(
                    $fulfillmentMethod->getOption('range_duration', 10)
                );
            }

            $disabled = !$allowEdit;
            if ($this->authorizationChecker->isGranted('ROLE_ADMIN') || 'collection' === $fulfillmentMethod->getType()) {
                $disabled = false;
            }

            $form
                ->add('minimumAmount', MoneyType::class, [
                    'label' => 'restaurant.contract.minimumCartAmount.label',
                    'disabled' => $disabled,
                ])
                ->add('orderingDelayDays', IntegerType::class, [
                    'label' => 'localBusiness.form.orderingDelayDays',
                    'mapped' => false,
                    'disabled' => $disabled,
                ])
                ->add('orderingDelayHours', IntegerType::class, [
                    'label' => 'localBusiness.form.orderingDelayHours',
                    'mapped' => false,
                    'disabled' => $disabled,
                ])
                ->add('preOrderingAllowed', CheckboxType::class, [
                    'label' => 'form.fulfillment_method.pre_ordering_allowed.label',
                    'help' => 'form.fulfillment_method.pre_ordering_allowed.help',
                    'required' => false,
                    'disabled' => $disabled,
                ])
                ->add('enabled', CheckboxType::class, [
                    'label' => 'basics.enabled',
                    'required' => false,
                    'disabled' => $disabled,
                    'attr' => [
                        'data-enable-fulfillment-method' => $fulfillmentMethod->getType()
                    ]
                ]);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $fulfillmentMethod = $form->getData();

            // Make sure there is no NULL value in the openingHours array
            $fulfillmentMethod->setOpeningHours(
                array_filter($fulfillmentMethod->getOpeningHours())
            );

            if ($form->has('allowEdit')) {
                $fulfillmentMethod->setOption(
                    'allow_edit',
                    $form->get('allowEdit')->getData()
                );
            }
            if ($form->has('rangeDuration')) {
                $rangeDuration = (int) $form->get('rangeDuration')->getData();
                $fulfillmentMethod->setOption(
                    'range_duration',
                    ($rangeDuration > 0 ? $rangeDuration : 10)
                );
            }
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $fulfillmentMethod = $event->getData();
            $form = $event->getForm();

            $orderingDelayMinutes = $fulfillmentMethod->getOrderingDelayMinutes();

            $cascade = CarbonInterval::minutes($orderingDelayMinutes)
                ->cascade()
                ->toArray();

            $orderingDelayDays = ($cascade['weeks'] * 7) + $cascade['days'];
            $orderingDelayHours = $cascade['hours'];

            $form->get('orderingDelayDays')->setData($orderingDelayDays);
            $form->get('orderingDelayHours')->setData($orderingDelayHours);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $fulfillmentMethod = $form->getData();

            $orderingDelayDays = $form->get('orderingDelayDays')->getData();
            $orderingDelayHours = $form->get('orderingDelayHours')->getData();

            $fulfillmentMethod->setOrderingDelayMinutes(
                ($orderingDelayDays * 60 * 24) + ($orderingDelayHours * 60)
            );
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => FulfillmentMethod::class,
        ));
    }
}
