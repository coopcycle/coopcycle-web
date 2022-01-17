<?php

namespace AppBundle\Utils;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Service\TimingRegistry;
use Carbon\Carbon;

class SortableRestaurantIterator extends \ArrayIterator
{
    public function __construct($array = [], TimingRegistry $timingRegistry)
    {
        $this->timingRegistry = $timingRegistry;

        $this->now = Carbon::now();

        $featured = array_filter($array, function (LocalBusiness $lb) {
            return $this->isFeatured($lb);
        });

        $notFeatured = array_filter($array, function (LocalBusiness $lb) {
            return !$this->isFeatured($lb);
        });

        usort($featured,    [$this, 'nextSlotComparator']);
        usort($notFeatured, [$this, 'nextSlotComparator']);

        parent::__construct(array_merge($featured, $notFeatured));
    }

    private function hasRange($timeInfo)
    {
        return !empty($timeInfo)
            && isset($timeInfo['range'])
            && is_array($timeInfo['range'])
            && count($timeInfo['range']) === 2;
    }

    private function isFeatured(LocalBusiness $lb)
    {
        $timeInfo = $this->timingRegistry->getForObject($lb);
        $hasRange = $this->hasRange($timeInfo);

        if (!$hasRange) {

            return false;
        }

        $start = Carbon::parse($timeInfo['range'][0]);

        if ($start->diffInHours($this->now) > 3) {

            return false;
        }

        return $lb->isFeatured();
    }

    public function nextSlotComparator(LocalBusiness $a, LocalBusiness $b)
    {
        $aTimeInfo = $this->timingRegistry->getForObject($a);
        $bTimeInfo = $this->timingRegistry->getForObject($b);

        if (empty($aTimeInfo) && empty($bTimeInfo)) {

            return 0;
        }

        if (empty($aTimeInfo)) {

            return 1;
        }

        if (empty($bTimeInfo)) {

            return -1;
        }

        $aHasRange = isset($aTimeInfo['range']) && is_array($aTimeInfo['range']) && count($aTimeInfo['range']) === 2;
        $bHasRange = isset($bTimeInfo['range']) && is_array($bTimeInfo['range']) && count($bTimeInfo['range']) === 2;

        if (!$aHasRange && !$bHasRange) {

            return 0;
        }

        if (!$aHasRange) {

            return 1;
        }

        if (!$bHasRange) {

            return -1;
        }

        $aStart = new \DateTime($aTimeInfo['range'][0]);
        $bStart = new \DateTime($bTimeInfo['range'][0]);

        if ($aStart === $bStart) {

            return 0;
        }

        return $aStart < $bStart ? -1 : 1;
    }
}
