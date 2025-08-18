<?php

namespace AppBundle\Api\State;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use AppBundle\Api\Dto\DeliveryFromTasksInput;
use AppBundle\Api\Dto\DeliveryInputDto;
use AppBundle\Api\Dto\TaskDto;
use AppBundle\DataType\TsRange;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Package;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Security\TokenStoreExtractor;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\Geocoder;
use AppBundle\Service\TagManager;
use AppBundle\Service\TimeSlotManager;
use AppBundle\Spreadsheet\ParseMetadataTrait;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class DeliveryProcessor implements ProcessorInterface
{
    use ParseMetadataTrait;

    public function __construct(
        private readonly TokenStoreExtractor $storeExtractor,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly DenormalizerInterface $denormalizer,
        private readonly IriConverterInterface $iriConverter,
        private readonly TagManager $tagManager,
        private readonly UserManagerInterface $userManager,
        private readonly Geocoder $geocoder,
        private readonly EntityManagerInterface $entityManager,
        private readonly DeliveryManager $deliveryManager,
        private readonly TimeSlotManager $timeSlotManager,
        private readonly LoggerInterface $logger
    )
    {
    }

    /**
     * @param DeliveryInputDto|DeliveryFromTasksInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Delivery
    {
        $store = $data->store ?? $this->storeExtractor->extractStore();

        if ($store instanceof Store) {
            //FIXME: move access controls to the operations in the Entities
            if (!$this->authorizationChecker->isGranted('ROLE_DISPATCHER') && !$this->authorizationChecker->isGranted('edit', $store)) {
                throw new AccessDeniedHttpException('');
            }
        }

        $isPutOperation = $operation instanceof Put;

        if ($data instanceof DeliveryInputDto) {
            $id = $uriVariables['id'] ?? null;
            if ($id && $isPutOperation) {
                $delivery = $this->entityManager->getRepository(Delivery::class)->find($id);
                if (null === $delivery) {
                    $this->logger->warning('Delivery not found', [
                        'id' => $id,
                    ]);
                    throw new InvalidArgumentException('delivery.id');
                }
            } else {
                $delivery = Delivery::create();
            }

            if (is_array($data->tasks) && count($data->tasks) > 0) {
                if ($isPutOperation) {
                    $tasks = array_map(fn(TaskDto $taskInput) => $this->transformIntoExistingTask($taskInput, $delivery->getTasks(), $store), $data->tasks);

                    //remove tasks that are not in the request
                    foreach ($delivery->getTasks() as $task) {
                        if (!in_array($task, $tasks)) {
                            $delivery->removeTask($task);
                            $this->entityManager->remove($task);
                        }
                    }

                } else {
                    $tasks = array_map(fn(TaskDto $taskInput) => $this->transformIntoNewTask($taskInput, $store), $data->tasks);
                    $delivery->withTasks(...$tasks);
                }



            } else {
                $this->transformIntoDeliveryTask($data->pickup, $delivery->getPickup(), Task::TYPE_PICKUP, $store);
                $this->transformIntoDeliveryTask($data->dropoff, $delivery->getDropoff(), Task::TYPE_DROPOFF, $store);
            }

        } elseif ($data instanceof DeliveryFromTasksInput) {
            $delivery = Delivery::createWithTasks(...$data->tasks);
        }

        if ($store) {
            $delivery->setStore($store);
        }

        $this->deliveryManager->setDefaults($delivery);

        if ($data instanceof DeliveryInputDto) {
            if ($data->packages) {
                $packageRepository = $this->entityManager->getRepository(Package::class);

                foreach ($data->packages as $p) {
                    $package = $packageRepository->findOneByNameAndStore($p->type, $store);
                    if ($package) {
                        $delivery->addPackageWithQuantity($package, $p->quantity);
                    }
                }
            }

            if ($data->weight) {
                $delivery->setWeight($data->weight);
            }
        }

        return $delivery;
    }

    private function transformIntoNewTask(
        TaskDto $data,
        Store|null $store = null
    ): Task
    {
        return $this->transformIntoTaskImpl($data, new Task(), $store);
    }

    /**
     * @param Task[] $tasks
     */
    private function transformIntoExistingTask(
        TaskDto $data,
        array $tasks,
        Store|null $store = null
    ): Task
    {
        foreach ($tasks as $task) {
            if ($task->getId() === $data->id) {
                return $this->transformIntoTaskImpl($data, $task, $store);
            }
        }

        $this->logger->warning('Task not found', [
            'id' => $data->id,
        ]);
        throw new InvalidArgumentException('task.id');
    }

    private function transformIntoDeliveryTask(
        TaskDto|null $data,
        Task $outputTask,
        string $taskType,
        Store|null $store = null,
    ): Task|null
    {
        if (null === $data) {
            return null;
        }

        return $this->transformIntoTaskImpl($data, $outputTask, $store, $taskType);
    }

    private function transformIntoTaskImpl(
        TaskDto $data,
        Task $task,
        Store|null $store = null,
        string|null $taskType = null,
    ): Task
    {
        $type = null;
        if ($data->type) {
            $type = strtoupper($data->type);
        }
        // Task type derived from a property name has higher priority than the one from the task object
        if ($taskType) {
            $type = $taskType;
        }

        if ($type) {
            $task->setType($type);
        }

        // Legacy props
        if (isset($data->doneAfter) && !isset($data->after)) {
            $data->after = $data->doneAfter;
        }
        if (isset($data->doneBefore) && !isset($data->before)) {
            $data->before = $data->doneBefore;
        }

        /**
         * @var TsRange|null $range
         */
        $range = null;

        if ($data->timeSlot) {

            $range = $data->timeSlot;

            $task->setAfter($range->getLower());
            $task->setBefore($range->getUpper());

        } elseif ($data->before || $data->after) {

            $tz = date_default_timezone_get();

            $after = null;
            if ($data->after) {
                $after = Carbon::parse($data->after)->tz($tz)->toDateTime();
            }

            $before = null;
            if ($data->before) {
                $before = Carbon::parse($data->before)->tz($tz)->toDateTime();
            }

            if ($after && $before) {
                $range = TsRange::create($after, $before);
            }

            if ($after) {
                $task->setAfter($after);
            }
            if ($before) {
                $task->setBefore($before);
            }
        }

        if ($range && $data->timeSlotUrl) {
            $timeSlot = $data->timeSlotUrl;

            if ($this->timeSlotManager->isChoice($timeSlot, $range)) {
                $task->setTimeSlot($timeSlot);
            } else {
                $this->logger->warning('Invalid time slot range: ', [
                    'timeSlot' => $timeSlot->getId(),
                    'range' => $range,
                ]);
                throw new InvalidArgumentException('task.timeSlot.invalid');
            }
        }

        /**
         * @var Address|null $address
         */
        $address = null;
        if ($data->address) {
            if (is_string($data->address)) {
                $addressIRI = $this->iriConverter->getIriFromResource(Address::class, operation: new GetCollection());
                if (0 === strpos($data->address, $addressIRI)) {
                    $address = $this->iriConverter->getResourceFromIri($data->address);
                } else {
                    $address = $this->geocoder->geocode($data->address);
                }
            } elseif (is_array($data->address)) {
                $address = $this->denormalizer->denormalize($data->address, Address::class, 'jsonld');
            } else {
                throw new InvalidArgumentException('task.address');
            }

            if (null === $address->getGeo()) {
                if ($data->latLng) {
                    [$latitude, $longitude] = $data->latLng;
                    $address->setGeo(new GeoCoordinates($latitude, $longitude));
                } else {
                    $geocoded = $this->geocoder->geocode($address->getStreetAddress());
                    $address->setGeo($geocoded->getGeo());
                }
            }
        }

        if ($address) {
            $task->setAddress($address);
        }

        if ($data->comments) {
            $task->setComments($data->comments);
        }

        if ($data->tags) {
            // Convert array of tag objects to array of tag slugs if needed
            if (is_array($data->tags) && !empty($data->tags) && isset($data->tags[0]['slug'])) {
                $data->tags = array_map(function($tag) {
                    return $tag['slug'];
                }, $data->tags);
            }
            $task->setTags($data->tags);
            $this->tagManager->update($task);
        }

        if ($data->weight) {
            $task->setWeight($data->weight);
        }

        if ($data->packages) {

            $packageRepository = $this->entityManager->getRepository(Package::class);

            foreach ($data->packages as $p) {
                $package = $packageRepository->findOneByNameAndStore($p->type, $store);
                if ($package) {
                    $task->setQuantityForPackage($package, $p->quantity);
                }
            }
        }

        if ($data->metadata) {
            // When editing a delivery, the metadata is passed as an array
            if (is_array($data->metadata)) {
                foreach ($data->metadata as $key => $value) {
                    $task->setMetadata($key, $value);
                }
            } elseif (is_string($data->metadata)) {
                $this->parseAndApplyMetadata($task, $data->metadata);
            }
        }

        if ($data->assignedTo) {
            $user = $this->userManager->findUserByUsername($data->assignedTo);
            if ($user && $user->hasRole('ROLE_COURIER')) {
                $task->assignTo($user);
            }
        }

        return $task;
    }
}
