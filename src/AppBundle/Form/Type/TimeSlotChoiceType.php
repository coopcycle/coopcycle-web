<?php

namespace AppBundle\Form\Type;

use AppBundle\Entity\TimeSlot;
use Carbon\Carbon;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;

class TimeSlotChoiceType extends AbstractType
{
    protected $translator;
    protected $country;
    protected $locale;

    public function __construct(TranslatorInterface $translator, string $country, string $locale)
    {
        $this->translator = $translator;
        $this->country = $country;
        $this->locale = $locale;
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

                [ $start, $end ] = $choice->getTimeRange();

                $calendar = Carbon::instance($choice->getDate())
                    ->locale($this->locale)
                    ->calendar(null, [
                        'sameDay' => '[' . $this->translator->trans('basics.today') . ']',
                        'nextDay' => '[' . $this->translator->trans('basics.tomorrow') . ']',
                        'nextWeek' => 'dddd',
                    ]);

                return $this->translator->trans('time_slot.human_readable', [
                    '%day%' => ucfirst(strtolower($calendar)),
                    '%start%' => $start,
                    '%end%' => $end,
                ]);
            },
            'choice_value' => function (TimeSlotChoice $choice = null) {
                return $choice ? (string) $choice : '';
            },
        ]);
        $resolver->setAllowedTypes('time_slot', ['null', TimeSlot::class]);
    }
}
