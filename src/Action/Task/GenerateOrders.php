<?php

declare(strict_types=1);

namespace AppBundle\Action\Task;

use AppBundle\Message\GenerateOrdersForDate;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

class GenerateOrders
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    )
    {
    }

    public function __invoke($data, Request $request): array
    {
        //get query parameters
        $queryParams = $request->query->all();

        $date = $queryParams['date'] ?? null;

        if (empty($date)) {
            throw new BadRequestHttpException('Date is required');
        }

        if (Carbon::parse($date . ' 23:59')->isPast()) {
            throw new BadRequestHttpException('Date must be in the future');
        }

        // On instances with many recurrence rules the generation takes up to a
        // minute, long enough for the client to drop the connection, so it runs
        // on a worker. The created tasks reach the dashboard on their own,
        // through the 'task:created' websocket broadcast.
        $this->messageBus->dispatch(new GenerateOrdersForDate($date));

        return [];
    }
}
