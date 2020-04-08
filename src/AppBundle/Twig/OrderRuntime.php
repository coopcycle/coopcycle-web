<?php

namespace AppBundle\Twig;

use AppBundle\DataType\TsRange;
use Carbon\Carbon;
use Twig\Extension\RuntimeExtensionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class OrderRuntime implements RuntimeExtensionInterface
{
    private $translator;
    private $locale;

    public function __construct(TranslatorInterface $translator, string $locale)
    {
        $this->translator = $translator;
        $this->locale = $locale;
    }

    public function timeRangeForHumans(TsRange $range)
    {
        $calendar = Carbon::instance($range->getLower())
            ->locale($this->locale)
            ->calendar(null, [
                'sameDay' => '[' . $this->translator->trans('basics.today') . ']',
                'nextDay' => '[' . $this->translator->trans('basics.tomorrow') . ']',
                'nextWeek' => 'dddd',
            ]);

        return $this->translator->trans('time_slot.human_readable', [
            '%day%'   => ucfirst(strtolower($calendar)),
            '%start%' => Carbon::instance($range->getLower())->locale($this->locale)->isoFormat('LT'),
            '%end%'   => Carbon::instance($range->getUpper())->locale($this->locale)->isoFormat('LT'),
        ]);
    }
}
