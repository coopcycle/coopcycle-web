<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Api\Dto\DeliveryInput;
use AppBundle\Api\Dto\TaskInput;
use AppBundle\DataType\TsRange;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryQuote;
use AppBundle\Entity\Package;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Api\Resource\RetailPrice;
use AppBundle\Entity\TimeSlot;
use AppBundle\Form\Type\TimeSlotChoiceLoader;
use AppBundle\Security\TokenStoreExtractor;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\Geocoder;
use AppBundle\Service\RoutingInterface;
use AppBundle\Service\TagManager;
use AppBundle\Spreadsheet\ParseMetadataTrait;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class DeliveryInputDataTransformer implements DataTransformerInterface
{
    use ParseMetadataTrait;

    public function __construct(
        private readonly TokenStoreExtractor $storeExtractor,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly ItemNormalizer $normalizer,
        private readonly IriConverterInterface $iriConverter,
        private readonly TagManager $tagManager,
        private readonly UserManagerInterface $userManager,
        private readonly Geocoder $geocoder,
        private readonly RoutingInterface $routing,
        private readonly EntityManagerInterface $entityManager,
        private readonly DeliveryManager $deliveryManager,
        private readonly string $country,
        private readonly LoggerInterface $logger
    )
    {
    }

    /**
     * @param DeliveryInput $data
     * {@inheritdoc}
     */
    public function transform($data, string $to, array $context = [])
    {
        $store = $data->store ?? $this->storeExtractor->extractStore();

        if ($store instanceof Store) {
            //FIXME: move access controls to the operations in the Entities
            if (!$this->authorizationChecker->isGranted('ROLE_DISPATCHER') && !$this->authorizationChecker->isGranted('edit', $store)) {
                throw new AccessDeniedHttpException('');
            }
        }

        if (is_array($data->tasks) && count($data->tasks) > 0) {
            $tasks = array_map(fn(Task|TaskInput $taskInput) => $this->transformTask($taskInput), $data->tasks);
            $delivery = Delivery::createWithTasks(...$tasks);
        } else {
            $pickup = $this->transformTask($data->pickup, Task::TYPE_PICKUP);
            $dropoff = $this->transformTask($data->dropoff, Task::TYPE_DROPOFF);

            if ($pickup && $dropoff) {
                $delivery = Delivery::createWithTasks($pickup, $dropoff);
            } else {
                $delivery = Delivery::create();
                $delivery->removeTask($delivery->getDropoff());

                $pickup = $delivery->getPickup();
                $dropoff = $data->dropoff;

                $pickup->setNext($dropoff);
                $dropoff->setPrevious($pickup);

                $delivery->addTask($dropoff);
            }
        }

        if ($store) {
            $delivery->setStore($store);
        }

        $this->deliveryManager->setDefaults($delivery);

        if ($data->packages) {
            $packageRepository = $this->entityManager->getRepository(Package::class);

            foreach ($data->packages as $p) {
                $package = $packageRepository->findOneByNameAndStore($p->type, $store);
                if ($package) {
                    $delivery->addPackageWithQuantity($package, $p->quantity);
                }
            }
        }

        $delivery->setWeight($data->weight ?? null);

        $coords = array_map(fn ($task) => $task->getAddress()->getGeo(), $delivery->getTasks());
        $distance = $this->routing->getDistance(...$coords);

        $delivery->setDistance(ceil($distance));

        if ($data->arbitraryPrice) {
            $delivery->setArbitraryPrice($data->arbitraryPrice);
        }

        return $delivery;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof RetailPrice) {
          return false;
        }

        if ($data instanceof DeliveryQuote) {
          return false;
        }

        if ($data instanceof Delivery) {
          return false;
        }

        return in_array($to, [ RetailPrice::class, DeliveryQuote::class, Delivery::class ]) && null !== ($context['input']['class'] ?? null);
    }

    public function transformTask(Task|TaskInput|null $data, $taskType = null, Store|null $store = null): Task|null {
        if (null === $data) {
            return null;
        }

        if ($data instanceof Task) {
            return $data;
        }

        $task = new Task();

        $type = null;
        if (isset($data->type)) {
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
         * @var TimeSlot $timeSlot
         */
        $timeSlot = null;

        if ($data->timeSlotUrl) {
            $timeSlot = $data->timeSlotUrl;
            $task->setTimeSlot($timeSlot);
        }

        if (isset($data->timeSlot)) {

            /**
             * @var TsRange $range
             */
            $range = $data->timeSlot;

            // Validate that the input time slot was selected from the given list of time slot choices (timeSlotUrl)
            if (null !== $timeSlot) {
                $choiceLoader = new TimeSlotChoiceLoader($timeSlot, $this->country);
                $choiceList = $choiceLoader->loadChoiceList();

                $choices = array_filter(
                    $choiceList->getChoices(),
                    function ($choice) use ($range) {
                        return $choice->contains($range);
                    }
                );

                if (0 === count($choices)) {
                    $this->logger->warning('Invalid time slot range: ', [
                        'timeSlot' => $timeSlot->getId(),
                        'range' => $range,
                    ]);
                    throw new InvalidArgumentException('task.timeSlot.invalid');
                }
            }

            $task->setAfter($range->getLower());
            $task->setBefore($range->getUpper());
        } elseif (isset($data->before) || isset($data->after)) {

            $tz = date_default_timezone_get();

            if (isset($data->after)) {
                $task->setAfter(
                    Carbon::parse($data->after)->tz($tz)->toDateTime()
                );
            }
            if (isset($data->before)) {
                $task->setBefore(
                    Carbon::parse($data->before)->tz($tz)->toDateTime()
                );
            }
        }

        /**
         * @var Address $address
         */
        $address = null;
        if (isset($data->address)) {
            if (is_string($data->address)) {
                $addressIRI = $this->iriConverter->getIriFromResourceClass(Address::class);
                if (0 === strpos($data->address, $addressIRI)) {
                    $address = $this->iriConverter->getItemFromIri($data->address);
                } else {
                    $address = $this->geocoder->geocode($data->address);
                }
            } elseif (is_array($data->address)) {
                $address = $this->normalizer->denormalize($data->address, Address::class, 'jsonld');
            }

            if (null === $address->getGeo()) {
                if (isset($data->latLng)) {
                    [ $latitude, $longitude ] = $data->latLng;
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

        if (isset($data->comments)) {
            $task->setComments($data->comments);
        }

        if (isset($data->tags)) {
            $task->setTags($data->tags);
            $this->tagManager->update($task);
        }

        // Ignore weight & packages for pickup tasks
        // @see https://github.com/coopcycle/coopcycle-web/issues/3461
        if ($task->isPickup()) {
            unset($data->weight);
            unset($data->packages);
        }

        if (isset($data->weight)) {
            $task->setWeight($data->weight);
        }

        if (isset($data->packages)) {

            $packageRepository = $this->entityManager->getRepository(Package::class);

            foreach ($data->packages as $p) {
                $package = $packageRepository->findOneByNameAndStore($p->type, $store);
                if ($package) {
                    $task->setQuantityForPackage($package, $p->quantity);
                }
            }
        }

        if (isset($data->metadata)) { // we support here metadata send as a string from a CSV file
            $this->parseAndApplyMetadata($task, $data->metadata);
        }

        if (isset($data->assignedTo)) {
            $user = $this->userManager->findUserByUsername($data->assignedTo);
            if ($user && $user->hasRole('ROLE_COURIER')) {
                $task->assignTo($user);
            }
        }

        return $task;
    }
}
