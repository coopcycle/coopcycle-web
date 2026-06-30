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
        $carbon = Carbon::instance($period->start)->locale($this->locale);
        if ($carbon->isToday()) {
            $day = $this->translator->trans('basics.today');
        } elseif ($carbon->isTomorrow()) {
            $day = $this->translator->trans('basics.tomorrow');
        } else {
            $day = $carbon->isoFormat('dddd');
        }

        $fmt = new \IntlDateFormatter($this->locale,
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::SHORT);

        return $this->translator->trans('time_slot.human_readable', [
            '%day%' => ucfirst(strtolower($day)),
            '%start%' => $fmt->format($period->start),
            '%end%' => $fmt->format($period->end),
        ]);
    }
}
