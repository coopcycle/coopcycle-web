<?php

namespace AppBundle\Api\State;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Service\TimingRegistry;
use AppBundle\Utils\Timing;
use AppBundle\Utils\TimeInfo;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProviderInterface;

final class RestaurantTimingProvider implements ProviderInterface
{
    public function __construct(
        private ItemProvider $provider,
        private TimingRegistry $timingRegistry)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var LocalBusiness */
        $restaurant = $this->provider->provide($operation, $uriVariables, $context);

        $result = $this->timingRegistry->getAllFulfillmentMethodsForObject($restaurant);

        $timing = new Timing();

        if (isset($result['delivery'])) {
            $timing->delivery = $this->toTimeInfo($result['delivery']);
        }

        if (isset($result['collection'])) {
            $timing->collection = $this->toTimeInfo($result['collection']);
        }

        return $timing;
    }

    private function toTimeInfo($data): TimeInfo
    {
        $timeInfo = new TimeInfo();

        // FIXME
        // Refactor this crap
        // https://github.com/coopcycle/coopcycle-web/issues/2213

        $rangeAsArray = isset($data['range']) && is_array($data['range']) ?
            $data['range'] : [ 'now', 'now' ];

        $range = new TsRange();
        $range->setLower(
            new \DateTime($rangeAsArray[0])
        );
        $range->setUpper(
            new \DateTime($rangeAsArray[1])
        );
        $timeInfo->range = $range;

        $timeInfo->today = $data['today'] ?? false;
        $timeInfo->fast  = $data['fast'] ?? false;
        $timeInfo->diff  = $data['diff'];

        return $timeInfo;
    }
}

