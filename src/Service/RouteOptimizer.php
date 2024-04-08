<?php

namespace AppBundle\Service;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\Tour;
use AppBundle\Vroom\RoutingProblem;
use AppBundle\Vroom\RoutingProblemNormalizer;
use AppBundle\Vroom\Job;
use AppBundle\Vroom\Shipment;
use AppBundle\Vroom\Vehicle;
use AppBundle\Entity\TaskCollection;
use Carbon\Carbon;
use Geocoder\Model\Coordinates;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A class to connect a given routing problem with the vroom api to return optimal results
 */
class RouteOptimizer
{
    private $vroomClient;

    public function __construct(HttpClientInterface $vroomClient, SettingsManager $settingsManager)
    {
        $this->vroomClient = $vroomClient;
        $this->settingsManager = $settingsManager;
    }

    /**
     * return a list of tasks sorted into an optimal route as obtained from the vroom api
     *
     * @param TaskCollection $taskCollection
     * @return array
     */
    public function optimize(TaskCollection $taskCollection)
    {
        $routingProblem = $this->createRoutingProblem($taskCollection);

        $normalizer = new RoutingProblemNormalizer();

        // TODO Catch Exception
        $response = $this->vroomClient->request('POST', '', [
            'headers' => ['Content-Type'=> 'application/json'],
            'body' => json_encode($normalizer->normalize($routingProblem)),
        ]);

        $tasks = $taskCollection->getTasks();
        $data = json_decode((string) $response->getContent(), true);

        $firstRoute = $data['routes'][0];
        array_shift($firstRoute['steps']);

        $jobIds = [];
        // extract task ids from steps
        foreach ($firstRoute['steps'] as $step) {
            if (array_key_exists('description', $step) && str_starts_with($step['description'], 'tour')) {
                $tourId = explode($step['description'], ':')[1];
                $tourTasks = array_filter($tasks, function ($task) use ($tourId) {
                    return $task->getTour() && $task->getTour()->getId() === $tourId;
                });
                array_push(
                    array_map(function ($task) { return $task->getId(); }, $tourTasks),
                    $jobIds
                );
            }
            else if (array_key_exists('id', $step)) {
                $jobIds[] = $step['id'];
            }
        }

        // sort tasks by ids in steps
        usort($tasks, function($a, $b) use($jobIds){
            $ka = array_search($a->getId(), $jobIds);
            $kb = array_search($b->getId(), $jobIds);
            return ($ka - $kb);
        });

        return $tasks;
    }

    /**
     * @param TaskCollection $taskCollection
     * @return RoutingProblem
     */
    public function createRoutingProblem(TaskCollection $taskCollection)
    {
        $routingProblem = new RoutingProblem();

        $deliveries = [];
        $tours = [];
        $tasks = $taskCollection->getTasks();

        foreach ($tasks as $task) {
            if (null !== $task->getTour() && !in_array($task->getTour(), $tours, true)) {
                $tours[] = $task->getTour();
            } else if (null !== $task->getDelivery() && !in_array($task->getDelivery(), $deliveries, true)) {
                $deliveries[] = $task->getDelivery();
            } else if (null == $task->getDelivery() && null == $task->getTour()) {
                $routingProblem->addJob(Task::toVroomJob($task));
            }
        }

        foreach ($tours as $tour) {
            $vroomStep = Tour::toVroomStep($tour);
            if ($vroomStep instanceof Shipment) {
                $routingProblem->addShipment($vroomStep);
            } else {
                // case of tour with 1 task
                $routingProblem->addJob($vroomStep);
            }
        }

        foreach ($deliveries as $delivery) {
            $routingProblem->addShipment(Delivery::toVroomShipment($delivery));
        }

        $firstTask = current($taskCollection->getTasks());

        $vehicle = new Vehicle(1, 'bike');
        $vehicle->setStart($firstTask->getAddress()->getGeo()->toGeocoderCoordinates());

        $routingProblem->addVehicle($vehicle);

        return $routingProblem;
    }

    /**
     * @see https://github.com/VROOM-Project/vroom/issues/313
     *
     * @param Address[] $pickups
     * @param Address $delivery
     * @param \SplObjectStorage $registry
     *
     * @return RoutingProblem
     */
    public function createRoutingProblemForPickupsAndDelivery(
        array $pickups, Address $delivery, \SplObjectStorage $registry)
    {
        $timeWindow = [
            (int) Carbon::now()->startOfDay()->format('U'),
            (int) Carbon::now()->endOfDay()->format('U')
        ];

        // We generate auto-increment identifiers using the SplObjectStorage
        $ids = 1;

        $registry->attach($delivery, $ids++);
        foreach ($pickups as $address) {
            $registry->attach($address, $ids++);
        }

        $routingProblem = new RoutingProblem();

        $deliveryJob = new Job();

        $deliveryJob->id = $registry[$delivery];
        $deliveryJob->location = [
            $delivery->getGeo()->getLongitude(),
            $delivery->getGeo()->getLatitude()
        ];
        $deliveryJob->time_windows = [
            $timeWindow
        ];

        foreach ($pickups as $address) {

            $job = new Job();

            $job->id = $registry[$address];
            $job->location = [
                $address->getGeo()->getLongitude(),
                $address->getGeo()->getLatitude()
            ];
            $job->time_windows = [
                $timeWindow
            ];

            $shipment = new Shipment();
            $shipment->pickup = $job;
            $shipment->delivery = $deliveryJob;

            $routingProblem->addShipment($shipment);
        }

        $shipments = $routingProblem->getShipments();

        // FIXME
        // Would make more sense to start from the warehouse/hub once this concept is introduced
        // In any case, we are only interested in the ordering, not the duration

        $vehicle = new Vehicle(1, 'bike');
        [ $latitude, $longitude ] = explode(',', $this->settingsManager->get('latlng'));
        $vehicle->setStart(new Coordinates($latitude, $longitude));

        $routingProblem->addVehicle($vehicle);

        return $routingProblem;
    }

    /**
     * @param Address[] $pickups
     * @param Address $delivery
     *
     * @return Address[]
     */
    public function optimizePickupsAndDelivery(array $pickups, Address $delivery)
    {
        $addresses = [];

        // Bail early, no need to optimize
        if (count($pickups) === 1) {

            return [ $pickups[array_key_first($pickups)], $delivery ];
        }

        $registry = new \SplObjectStorage();
        $problem = $this->createRoutingProblemForPickupsAndDelivery($pickups, $delivery, $registry);

        $solution = $this->execute($problem);

        $addressesById = [];
        foreach ($registry as $address) {
            $id = $registry[$address];
            $addressesById[$id] = $address;
        }

        foreach ($solution['routes'][0]['steps'] as $step) {
            if ('pickup' === $step['type']) {
                $addresses[] = $addressesById[$step['id']];
            }
        }

        $addresses[] = $delivery;

        return $addresses;
    }

    /**
     * @param RoutingProblem $problem
     * @return array
     */
    public function execute(RoutingProblem $problem)
    {
        $normalizer = new RoutingProblemNormalizer();

        $response = $this->vroomClient->request('POST', '', [
            'headers' => ['Content-Type'=> 'application/json'],
            'body' => json_encode($normalizer->normalize($problem)),
        ]);

        return json_decode((string) $response->getContent(), true);
    }
}
