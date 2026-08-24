<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc;

use AppBundle\Integration\Rdc\Api\RdcClientInterface;

final readonly class RdcContext
{
    public function __construct(
        public RdcClientInterface $client,
        public string $serviceId,
        public string $activityId,
    ) {}
}