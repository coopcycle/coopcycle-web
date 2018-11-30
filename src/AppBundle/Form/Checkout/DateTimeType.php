<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Form\AddressType;
use AppBundle\Utils\ShippingDateFilter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DateTimeType extends AbstractType
{
    private $shippingDateFilter;

    public function __construct(ShippingDateFilter $shippingDateFilter)
    {
        $this->shippingDateFilter = $shippingDateFilter;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $groups = [];
        foreach ($options['choices'] as $date) {
            $date = new \DateTime($date);
            $groups[$date->format('Y-m-d')][] = $date;
        }

        $dateChoices = [];
        foreach (array_keys($groups) as $date) {
            $date = new \DateTime($date);
            $dateChoices[$date->format('Y-m-d')] = $date->format('Y-m-d');
        }

        $timeChoices = [];
        foreach (current($groups) as $date) {
            $timeChoices[$date->format('H:i')] = $date->format('H:i');
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
