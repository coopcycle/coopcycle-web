<?php

namespace AppBundle\Twig;

use AppBundle\DataType\TsRange;
use AppBundle\Utils\TsRangeFormatter;
use Carbon\Carbon;
use Twig\Extension\RuntimeExtensionInterface;
use Redis;
use Symfony\Contracts\Translation\TranslatorInterface;

class OrderRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private TranslatorInterface $translator,
        private Redis $redis,
        private string $locale,
        private TsRangeFormatter $tsRangeFormatter,
    ) {}


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

        $carbon = Carbon::instance($range->getLower())->locale($this->locale);
        if ($carbon->isToday()) {
            return $this->translator->trans('time_range.same_day', ['%range%' => $rangeAsText]);
        } elseif ($carbon->isTomorrow()) {
            return $this->translator->trans('time_range.next_day', ['%range%' => $rangeAsText]);
        } elseif ($carbon->isYesterday()) {
            return $this->translator->trans('time_range.last_day', ['%range%' => $rangeAsText]);
        } elseif ($carbon->isFuture() && $carbon->diffInDays() < 7) {
            return $carbon->isoFormat(sprintf('dddd [%s]', $rangeAsText));
        }
        return $sameElse;
    }

    /**
     * @param TsRange|string $range
     * @return string
     */
    public function timeRangeForHumansShort($range): string
    {
        if (!$range instanceof TsRange) {
            $range = TsRange::parse($range);
        }

        return $this->tsRangeFormatter->formatShort($range);
    }

    public function hasDelayConfigured(): bool
    {
        if ($value = $this->redis->get('foodtech:dispatch_delay_for_pickup')) {
            return intval($value) > 0;
        }

        return false;
    }
}
