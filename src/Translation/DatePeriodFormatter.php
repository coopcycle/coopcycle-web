<?php

namespace AppBundle\Translation;

use Carbon\Carbon;
use Symfony\Contracts\Translation\TranslatorInterface;

class DatePeriodFormatter
{
    protected $translator;
    protected $locale;

    public function __construct(TranslatorInterface $translator, string $locale)
    {
        $this->translator = $translator;
        $this->locale = $locale;
    }

    public function toHumanReadable(\DatePeriod $period): string
    {
        $calendar = Carbon::instance($period->start)
            ->locale($this->locale)
            ->calendar(null, [
                'sameDay' => '[' . $this->translator->trans('basics.today') . ']',
                'nextDay' => '[' . $this->translator->trans('basics.tomorrow') . ']',
                'nextWeek' => 'dddd',
            ]);

        $fmt = new \IntlDateFormatter($this->locale,
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::SHORT);

        return $this->translator->trans('time_slot.human_readable', [
            '%day%' => ucfirst(strtolower($calendar)),
            '%start%' => $fmt->format($period->start),
            '%end%' => $fmt->format($period->end),
        ]);
    }
}
