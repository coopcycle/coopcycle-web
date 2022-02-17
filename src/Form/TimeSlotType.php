<?php

namespace AppBundle\Form;

use AppBundle\Entity\TimeSlot;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class TimeSlotType extends AbstractType
{
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
            	'label' => 'form.time_slot.name.label'
            ])
            ->add('interval', ChoiceType::class, [
                'label' => 'form.time_slot.interval.label',
                'choices'  => [
                    $this->translator->trans('basics.days', ['%count%' => 1]) => '1 days',
                    $this->translator->trans('basics.days', ['%count%' => 2]) => '2 days',
                    $this->translator->trans('basics.days', ['%count%' => 3]) => '3 days',
                    $this->translator->trans('basics.days', ['%count%' => 4]) => '4 days',
                    $this->translator->trans('basics.days', ['%count%' => 5]) => '5 days',
                    $this->translator->trans('basics.days', ['%count%' => 6]) => '6 days',
                    $this->translator->trans('basics.weeks',['%count%' => 1]) => '1 week',
                    $this->translator->trans('basics.weeks',['%count%' => 2]) => '2 weeks',
                ],
            ])
            ->add('workingDaysOnly', CheckboxType::class, [
                'label' => 'form.time_slot.working_days_only.label',
                'required' => false,
            ])
            ->add('sameDayCutoff', TimeType::class, [
                'label' => 'form.time_slot.same_day_cutoff.label',
                'required' => false,
                'input' => 'string',
                'input_format' => 'H:i',
                'help' => 'form.time_slot.same_day_cutoff.help',
                'minutes' => [0, 15, 30, 60],
            ])
            ->add('openingHours', CollectionType::class, [
                'entry_type' => HiddenType::class,
                'entry_options' => [
                    'error_bubbling' => false
                ],
                'required' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'label' => false,
                'error_bubbling' => false,
            ])
            ;

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $timeSlot = $event->getData();

            $priorNotice = $timeSlot->getPriorNotice();

            $hours = 0;
            if (null !== $priorNotice) {
                if (1 === preg_match('/^(?<value>[0-9]+) hours?$/', $priorNotice, $matches)) {
                    $hours = (int) $matches['value'];
                }
            }

            $form->add('priorNoticeHours', IntegerType::class, [
                'label' => 'form.time_slot.prior_notice_hours.label',
                'mapped' => false,
                'data' => $hours,
                'attr' => [
                    'min' => 0
                ]
            ]);
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $timeSlot = $event->getData();

            $priorNoticeHours = $form->get('priorNoticeHours')->getData();
            $timeSlot->setPriorNotice(sprintf('%d %s', $priorNoticeHours, ($priorNoticeHours > 1 ? 'hours' : 'hour')));
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => TimeSlot::class,
        ));
    }
}
