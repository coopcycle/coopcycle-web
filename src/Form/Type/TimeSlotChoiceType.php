<?php

namespace AppBundle\Form\Type;

use AppBundle\Entity\TimeSlot;
use AppBundle\Translation\DatePeriodFormatter;
use Carbon\Carbon;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TimeSlotChoiceType extends AbstractType
{
    protected $datePeriodFormatter;
    protected $country;

    public function __construct(DatePeriodFormatter $datePeriodFormatter, string $country)
    {
        $this->datePeriodFormatter = $datePeriodFormatter;
        $this->country = $country;
    }

    public function getParent()
    {
        return ChoiceType::class;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'time_slot' => null,
            'choice_loader' => function (Options $options) {

                if (isset($options['disabled']) && isset($options['data']) && true === $options['disabled']) {
                    return new CallbackChoiceLoader(function () use ($options) {
                        return [ $options['data'] ];
                    });
                }

                return new TimeSlotChoiceLoader($options['time_slot'], $this->country);
            },
            'choice_label' => function(TimeSlotChoice $choice) {

                return $this->datePeriodFormatter->toHumanReadable($choice->toDatePeriod());
            },
            'choice_value' => function (TimeSlotChoice $choice = null) {
                return $choice ? (string) $choice : '';
            },
        ]);
        $resolver->setAllowedTypes('time_slot', ['null', TimeSlot::class]);
    }
}
