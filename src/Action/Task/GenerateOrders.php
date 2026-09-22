<?php

declare(strict_types=1);

namespace AppBundle\Action\Task;

use AppBundle\Entity\Task\RecurrenceRuleGeneration;
use AppBundle\Entity\Task\RecurrenceRuleGenerationRepository;
use AppBundle\Message\GenerateOrdersForDate;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

class GenerateOrders
{
    public function __construct(
        private readonly RecurrenceRuleGenerationRepository $repository,
        private readonly MessageBusInterface $messageBus,
    )
    {
    }

    public function __invoke(Request $request): RecurrenceRuleGeneration
    {
        $date = $request->query->get('date');

        if (empty($date)) {
            throw new BadRequestHttpException('Date is required');
        }

        $date = Carbon::parse($date)->format('Y-m-d');

        if (Carbon::parse($date . ' 23:59')->isPast()) {
            throw new BadRequestHttpException('Date must be in the future');
        }

        // Whether this request owns the date is decided by the database, not by
        // us, so a run already in progress is never started a second time.
        if ($this->repository->claim($date)) {
            // On instances with many recurrence rules the generation takes up to
            // a minute, long enough for the client to drop the connection, so it
            // runs on a worker. The created tasks reach the dashboard on their
            // own, through the 'task:created' websocket broadcast.
            $this->messageBus->dispatch(new GenerateOrdersForDate($date));
        }

        $generation = $this->repository->findOneByDate($date);

        if (is_null($generation)) {
            throw new \RuntimeException(sprintf('No generation was recorded for date "%s"', $date));
        }

        return $generation;
    }
}
