<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Form\AddressType;
use Carbon\Carbon;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DateTimeType extends AbstractType
{
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $now = Carbon::now();

        $tomorrow = clone $now;
        $tomorrow->modify('+1 day');

        $groups = [];
        foreach ($options['choices'] as $date) {
            $date = new \DateTime($date);
            $groups[$date->format('Y-m-d')][] = $date;
        }

        $dateFormatter = \IntlDateFormatter::create(
            $this->translator->getLocale(),
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::NONE
        );

        $dateChoices = [];
        foreach (array_keys($groups) as $date) {
            $date = new \DateTime($date);

            $label = $dateFormatter->format($date->getTimestamp());
            if ($date->format('Y-m-d') === $now->format('Y-m-d')) {
                $label = $this->translator->trans('basics.today');
            } elseif ($date->format('Y-m-d') === $tomorrow->format('Y-m-d')) {
                $label = $this->translator->trans('basics.tomorrow');
            }

            $dateChoices[$label] = $date->format('Y-m-d');
        }

        $timeFormatter = \IntlDateFormatter::create(
            $this->translator->getLocale(),
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::SHORT
        );

        $timeChoices = [];
        foreach (current($groups) as $date) {
            $label = $timeFormatter->format($date->getTimestamp());
            $timeChoices[$label] = $date->format('H:i');
        }

        $builder
            ->add('date', ChoiceType::class, [
                'mapped' => false,
                'choices' => $dateChoices,
                'expanded' => false,
                'multiple' => false,
            ])
            ->add('time', ChoiceType::class, [
                'mapped' => false,
                'choices' => $timeChoices,
                'expanded' => false,
                'multiple' => false,
            ]);

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $data = $event->getData();
            $form = $event->getForm();

            $date = $form->get('date')->getData();
            $time = $form->get('time')->getData();

            if ($date && $time) {
                $dateTime = \DateTime::createFromFormat('Y-m-d H:i', sprintf('%s %s', $date, $time));
                $event->setData($dateTime);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['choices'] = $options['choices'];

        if ($options['help_message']) {
            $view->vars['help'] = $options['help_message'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => null,
            'choices' => [],
            'compound' => true,
            'help_message' => null,
        ));
    }
}
