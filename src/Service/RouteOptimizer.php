<?php

namespace AppBundle\Service;

use ApiPlatform\Core\Api\IriConverterInterface;
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
use AppBundle\Entity\TaskList;
use Carbon\Carbon;
use Geocoder\Model\Coordinates;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A class to connect a given routing problem with the vroom api to return optimal results
 */
class RouteOptimizer
{
    public function __construct(
        private HttpClientInterface $vroomClient,
        private SettingsManager $settingsManager,
        private LoggerInterface $logger,
        private IriConverterInterface $iriConverter
    )
    {}

    /**
     * return a list of tasks sorted into an optimal route as obtained from the vroom api
     *
     * @param TaskList $taskCollection
     * @return array
     */
    public function optimize(TaskList $taskCollection)
    {
        $routingProblem = $this->createRoutingProblem($taskCollection);

        $normalizer = new RoutingProblemNormalizer();

        $this->logger->info("Input data for optimization routing problem");
        $this->logger->info(print_r($normalizer->normalize($routingProblem), true));

        // TODO Catch Exception
        $response = $this->vroomClient->request('POST', '', [
            'headers' => ['Content-Type'=> 'application/json'],
            'body' => json_encode($normalizer->normalize($routingProblem)),
        ]);

        $data = json_decode((string) $response->getContent(), true);

        $firstRoute = $data['routes'][0];
        // remove the first result which is the starting point, for now equal of the first task of the list
        array_shift($firstRoute['steps']);

        $this->logger->info("Route optimization result");
        $this->logger->info(print_r($data, true));

        $jobDescriptions = [];

        foreach ($firstRoute['steps'] as $step) {
            array_push($jobDescriptions, $step['description']);
        }

        $items = $taskCollection->getItems();
        $iriConverter = $this->iriConverter;

        $res = array_filter( // eliminate NULL results as in the case of delivery with pickup/dropoffs assigned to different riders
            array_map(
                function($desc) use ($iriConverter, $items) {
                    $res = $items->filter(function($item) use ($desc, $iriConverter) {
                        return $item->getItemIri($iriConverter) == $desc;
                    });
                    if (count($res) > 0) { // FIXME : handle the case were we optimized a delivery which pickup and dropoff has been assigned to different riders
                        return $res->current();
                    }
                },
                $jobDescriptions
            )
        );

        // add empty tours that were not sent to Vroom
        $res = array_merge(
            $res,
            $items->filter(function($item) {
                return $item->getTour() !== null && count($item->getTour()->getTasks()) === 0;
            })->toArray()
        );

        return [
            "solution" => $res,
            "unassignedCount" => $data["summary"]["unassigned"]
        ];
    }

    /**
     * @param TaskList $taskCollection
     * @return RoutingProblem
     */
    public function createRoutingProblem(TaskList $taskCollection)
    {
        $routingProblem = new RoutingProblem();

        $tours = [];
        $items = $taskCollection->getItems();

        foreach ($items as $item) {
            if (null !== $item->getTour() && !in_array($item->getTour(), $tours, true)) {
                $tours[] = $item->getTour();
            } else if (null == $item->getTour()) {
                $task = $item->getTask();
                $delivery =$task->getDelivery();
                // FIXME : may not work as expected now that we allow to split deliveries pickup/dropoffs between riders
                if (null !== $delivery && $task->isDropoff()) {
                    $routingProblem->addShipment(Delivery::toVroomShipment(
                        $delivery,
                        $task,
                        $this->iriConverter->getItemIriFromResourceClass(Task::class, ['id' => $delivery->getPickup()->getId()]),
                        $this->iriConverter->getItemIriFromResourceClass(Task::class, ['id' => $task->getId()])
                    ));
                } else if (null == $task->getDelivery()) {
                    $routingProblem->addJob(Task::toVroomJob(
                        $task,
                        $this->iriConverter->getItemIriFromResourceClass(Task::class, ['id' => $task->getId()])
                    ));
                }
            }
        }

        foreach ($tours as $tour) {
            if (count($tour->getTasks())) {
                $vroomStep = Tour::toVroomStep(
                    $tour,
                    $this->iriConverter->getItemIriFromResourceClass(Tour::class, ['id' => $tour->getId()])
                );
                $routingProblem->addJob($vroomStep);
            }
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
