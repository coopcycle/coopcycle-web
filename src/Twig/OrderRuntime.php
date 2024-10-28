<?php

namespace AppBundle\Twig;

use AppBundle\DataType\TsRange;
use Carbon\Carbon;
use Twig\Extension\RuntimeExtensionInterface;
use Redis;
use Symfony\Contracts\Translation\TranslatorInterface;

class OrderRuntime implements RuntimeExtensionInterface
{
    private $translator;
    private $redis;
    private $locale;

    public function __construct(TranslatorInterface $translator, Redis $redis, string $locale)
    {
        $this->translator = $translator;
        $this->redis = $redis;
        $this->locale = $locale;
    }

    /**
     * @param TsRange|string $range
     * @return string
     */
    public function timeRangeForHumans($range)
    {
        if (!$range instanceof TsRange) {
            $range = TsRange::parse($range);
        }

        if (!$range) {

            return $this->translator->trans('order.shippingTimeRange.notAvailable', [], 'validators');
        }

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

    /**
     * @param TsRange|string $range
     * @return string
     */
    public function timeRangeForHumansShort($range)
    {
        if (!$range instanceof TsRange) {
            $range = TsRange::parse($range);
        }

        $lower = Carbon::instance($range->getLower())->locale($this->locale);

        $rangeAsText = implode(' - ', [
            $lower->isoFormat('LT'),
            Carbon::instance($range->getUpper())->locale($this->locale)->isoFormat('LT')
        ]);

        return sprintf('%s %s',
            $lower->isoFormat('L'),
            $rangeAsText
        );
    }

    public function hasDelayConfigured(): bool
    {
        if ($value = $this->redis->get('foodtech:dispatch_delay_for_pickup')) {
            return intval($value) > 0;
        }

        return false;
    }
}
