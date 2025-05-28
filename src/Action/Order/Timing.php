<?php

namespace AppBundle\Action\Order;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Utils\OrderTimeHelper;
use Carbon\Carbon;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class Timing extends AbstractController
{
    public function __construct(private OrderTimeHelper $orderTimeHelper)
    {}

    public function __invoke(Order $data): JsonResponse
    {
        $timing = $this->orderTimeHelper->getTimeInfo($data);

        $timing['choices'] = array_map(function ($range) {
            [ $lower, $upper ] = $range;

            return Carbon::instance(Carbon::parse($lower))
                ->average(Carbon::parse($upper))
                ->format(\DateTime::ATOM);
        }, $timing['ranges']);

        return new JsonResponse($timing);
    }
}
