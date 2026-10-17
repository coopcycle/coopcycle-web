<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\TourInput;
use AppBundle\Api\Precondition\IfMatch;
use AppBundle\Entity\Task;
use AppBundle\Entity\Tour;
use AppBundle\Service\RoutingInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\DBAL\LockMode;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class TourProcessor implements ProcessorInterface
{
    public function __construct(
        private RoutingInterface $routing,
        private ProcessorInterface $persistProcessor,
        private ItemProvider $provider,
        private RequestStack $requestStack,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger = new NullLogger())
    {}

    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if ($operation instanceof Put) {

            /** @var Tour */
            $tour = $this->provider->provide($operation, $uriVariables, $context);

            $this->assertNotStale($tour);

            if (!empty($data->name)) {
                $tour->setName($data->name);
            }

            if (!empty($data->date)) {
                $tour->setDate(new \DateTime($data->date));
            }

            $tour->setTasks($data->tasks);

            // Moving a tour to another day propagates the date to its tasks,
            // keeping each task's time-of-day untouched.
            if (!empty($data->date)) {
                $this->moveTasksToDate($tour->getTasks(), $tour->getDate());
            }

        } else {

            $tour = new Tour();

            $tour->setName($data->name);
            $tour->setDate(new \DateTime($data->date));

            foreach ($data->tasks as $task) {
                $tour->addTask($task);
            }
        }

        $tasks = $tour->getTasks();
        $distance = 0;

        // Distance can't be calculated without at least 2 tasks
        if (count($tasks) >= 2) {
            $coords = array_map(fn ($task) => $task->getAddress()->getGeo(), $tasks);
            $distance = $this->routing->getDistance(...$coords);
        }

        $tour->setDistance(ceil($distance));

        return $this->persistProcessor->process($tour, $operation, $uriVariables, $context);
    }

    /**
     * A tour is replaced wholesale: every task the payload omits is dropped
     * from it. That makes a write built from a stale read destructive rather
     * than merely out of date, and nothing in the request distinguishes the
     * two — "the dispatcher removed these tasks" and "this client has not
     * heard about them yet" are the same bytes.
     *
     * So when the client tells us which version it is working from, hold it to
     * it. `If-Match` is optional: clients that do not send it keep the old
     * behaviour, which is what lets this ship without a coordinated release.
     *
     * @see https://github.com/coopcycle/coopcycle-web/issues/5372
     */
    private function assertNotStale(Tour $tour): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

        $expected = IfMatch::expectedVersion($request);

        if (null === $expected) {
            // How much of the fleet still writes without a precondition. This
            // is what tells us when `If-Match` can be made mandatory, and for
            // which tenants — see the rollout in #5372.
            // The message is kept constant and the client put in the context,
            // so this collapses into one Datadog pattern instead of one per
            // User-Agent, on the channel the unassignment warnings are read on.
            $this->logger->info('Tour write without If-Match', [
                'user_agent' => $request->headers->get('User-Agent', 'unknown client'),
            ]);

            return;
        }

        try {
            // Also arms the version check on the UPDATE itself, so a tour that
            // changes between here and the flush is caught too — that window
            // spans an OSRM round trip, so it is not theoretical. Doctrine
            // raises the same exception there, mapped to 409 in
            // `api_platform.yaml` since it escapes this try/catch.
            $this->entityManager->lock($tour, LockMode::OPTIMISTIC, $expected);
        } catch (OptimisticLockException $e) {
            throw new ConflictHttpException(sprintf(
                'Tour %d has changed since version %d was read; refetch it and retry',
                $tour->getId(),
                $expected
            ), $e);
        }
    }

    /**
     * @param Task[] $tasks
     */
    private function moveTasksToDate(array $tasks, \DateTime $date): void
    {
        $year  = (int) $date->format('Y');
        $month = (int) $date->format('m');
        $day   = (int) $date->format('d');

        foreach ($tasks as $task) {
            $after = $task->getAfter();
            if (null !== $after) {
                // Set a new instance so Doctrine detects the change
                // (mutating a DateTime in place goes unnoticed).
                $task->setAfter((clone $after)->setDate($year, $month, $day));
            }

            $before = $task->getBefore();
            if (null !== $before) {
                $task->setBefore((clone $before)->setDate($year, $month, $day));
            }
        }
    }
}
