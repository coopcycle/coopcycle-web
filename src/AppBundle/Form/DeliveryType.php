<?php

namespace AppBundle\Form;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
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
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints;


class DeliveryType extends AbstractType
{
    private $authorizationChecker;
    private $translator;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, TranslatorInterface $translator)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->translator = $translator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $vehicleChoices = [
            $this->translator->trans('form.delivery.vehicle.VEHICLE_BIKE') => Delivery::VEHICLE_BIKE,
            $this->translator->trans('form.delivery.vehicle.VEHICLE_CARGO_BIKE') => Delivery::VEHICLE_CARGO_BIKE,
        ];

        $isAdmin = $this->authorizationChecker->isGranted('ROLE_ADMIN');

        $builder
            ->add('weight', NumberType::class, [
                'required' => false,
                'label' => 'form.delivery.weight.label'
            ])
            ->add('price', MoneyType::class)
            ->add('pickup', TaskType::class, [
                'mapped' => false,
                'label' => 'form.delivery.pickup.label'
            ])
            ->add('dropoff', TaskType::class, [
                'mapped' => false,
                'label' => 'form.delivery.dropoff.label'
            ])
            ->add('vehicle', ChoiceType::class, [
                'required' => true,
                'choices'  => $vehicleChoices,
                'placeholder' => 'form.delivery.vehicle.placeholder',
                'label' => 'form.delivery.vehicle.label',
                'multiple' => false,
                'expanded' => true,
            ]);

        if ($options['with_stores']) {
            $builder->add('store', EntityType::class, array(
                'class' => Store::class,
                'choice_label' => 'name',
                'required' => false,
                'label' => 'form.delivery.store.label',
                'attr' => [
                    'placeholder' => 'form.delivery.store.placeholder'
                ]
            ));
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

        $builder->get('pickup')->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($options) {
                $delivery = $event->getForm()->getParent()->getData();
                foreach ($delivery->getTasks() as $task) {
                    if ($task->getType() === Task::TYPE_PICKUP) {
                        $event->setData($task);
                    }
                }
            }
        );

        $builder->get('dropoff')->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($options) {
                $delivery = $event->getForm()->getParent()->getData();
                foreach ($delivery->getTasks() as $task) {
                    if ($task->getType() === Task::TYPE_DROPOFF) {
                        $event->setData($task);
                    }
                }
            }
        );

        $builder->addEventListener(
            FormEvents::POST_SET_DATA,
            function (FormEvent $event) use ($options, $isAdmin) {

                $form = $event->getForm();
                $delivery = $form->getData();

                if (!$isAdmin) {
                    $priceFieldConfig = $event->getForm()->get('price')->getConfig();
                    $priceFieldOptions = $priceFieldConfig->getOptions();
                    $priceFieldOptions['attr'] = ['disabled' => true];
                    $event->getForm()->add('price', MoneyType::class, $priceFieldOptions);
                }

                if (false === $options['free_pricing'] && null !== $options['pricing_rule_set']) {
                    $event->getForm()->get('pricingRuleSet')->setData($options['pricing_rule_set']->getId());
                }
            }
        );

        // Make sure legacy "date" field is set
        $builder->get('dropoff')->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {
            $delivery = $event->getForm()->getParent()->getData();
            $data = $event->getData();
            $delivery->setDate(new \DateTime($data['doneBefore']));
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Delivery::class,
            'free_pricing' => true,
            'pricing_rule_set' => null,
            'with_stores' => false
        ));
    }
}
