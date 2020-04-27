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
        $rangeAsText = $this->translator->trans('time_range', [
            '%start%' => Carbon::instance($range->getLower())->locale($this->locale)->isoFormat('LT'),
            '%end%'   => Carbon::instance($range->getUpper())->locale($this->locale)->isoFormat('LT'),
        ]);

        $sameElse =
            $this->translator->trans('time_range.same_else', ['%range%' => $rangeAsText]);

        return Carbon::instance($range->getLower())
            ->locale($this->locale)
            ->calendar(null, [
                'sameDay'  => $this->translator->trans('time_range.same_day', ['%range%' => $rangeAsText]),
                'nextDay'  => $this->translator->trans('time_range.next_day', ['%range%' => $rangeAsText]),
                'nextWeek' => sprintf('dddd [%s]', $rangeAsText),
                'lastDay'  => $this->translator->trans('time_range.last_day', ['%range%' => $rangeAsText]),
                'lastWeek' => $sameElse,
                'sameElse' => $sameElse,
            ]);
    }
}
