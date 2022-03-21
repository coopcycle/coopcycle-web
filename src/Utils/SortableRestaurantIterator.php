<?php

namespace AppBundle\Utils;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Service\TimingRegistry;

class SortableRestaurantIterator extends \ArrayIterator
{
    public function __construct($array, TimingRegistry $timingRegistry)
    {
        $this->timingRegistry = $timingRegistry;

        usort($array, [$this, 'nextSlotComparator']);

        parent::__construct($array);
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
