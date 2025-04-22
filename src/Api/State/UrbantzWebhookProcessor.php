<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Resource\UrbantzWebhook;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Entity\Urbantz\Delivery as UrbantzDelivery;
use AppBundle\Entity\Urbantz\Hub as UrbantzHub;
use AppBundle\Security\TokenStoreExtractor;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\TaskManager;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;

class UrbantzWebhookProcessor implements ProcessorInterface
{
    private static $onTaskChangedEvents = ['DISCARDED'];

    public function __construct(
        private DeliveryRepository $deliveryRepository,
        private TaskManager $taskManager,
        private PhoneNumberUtil $phoneNumberUtil,
        private EntityManagerInterface $entityManager,
        private TokenStoreExtractor $storeExtractor,
        private DeliveryManager $deliveryManager,
        private LoggerInterface $logger,
        private string $country)
    {}

    /**
     * @param UrbantzWebhook $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $event = $data->id;

        foreach ($data->tasks as $task) {
            switch ($event) {
                case UrbantzWebhook::TASKS_ANNOUNCED:
                    $data->deliveries[] = $this->onTaskAnnounced($task);
                    $data->hub = $task['hub'];
                    break;
                case UrbantzWebhook::TASK_CHANGED:
                    if ($delivery = $this->onTaskChanged($task)) {
                        $data->deliveries[] = $delivery;
                    }
                    break;
            }
        }

        foreach ($data->deliveries as $delivery) {
            if (!$this->entityManager->contains($delivery)) {
                $this->entityManager->persist($delivery);
            }
        }

        $this->entityManager->flush();

        return $data;
    }

    private function onTaskAnnounced(array $task): Delivery
    {
        $delivery = new Delivery();

        $address = new Address();

        $streetAddress = sprintf('%s, %s',
            $task['source']['street'],
            ($task['source']['zip'] . ' ' . $task['source']['city'])
        );

        $address->setStreetAddress($streetAddress);

        [ $longitude, $latitude ] = $task['location']['location']['geometry'] ?? [];

        if ($latitude && $longitude) {
            $address->setGeo(new GeoCoordinates($latitude, $longitude));
        } else {
            // $geoAddr = $this->geocoder->geocode($streetAddress);
            // $address->setGeo($geoAddr->getGeo());
        }

        $contactName = $task['contact']['person'] ?? $task['contact']['name'];
        $address->setContactName($contactName);

        $description = $task['instructions'] ?? '';
        if (!empty($description)) {
            $address->setDescription($description);
        }

        try {
            $phone = $task['contact']['phone'] ?? null;
            if ($phone) {
                $address->setTelephone(
                    $this->phoneNumberUtil->parse($phone, strtoupper($this->country))
                );
            }
        } catch (NumberParseException $e) {}

        $delivery->getDropoff()->setAddress($address);

        $tz = date_default_timezone_get();

        $delivery->getDropoff()->setAfter(
            Carbon::parse($task['timeWindow']['start'])->tz($tz)->toDateTime()
        );
        $delivery->getDropoff()->setBefore(
            Carbon::parse($task['timeWindow']['stop'])->tz($tz)->toDateTime()
        );

        $pickupComments = '';

        if (isset($task['hubName'])) {
            $pickupComments .= "{$task['hubName']}\n\n";
        }

        $pickupComments .= "Commande n° {$task['taskId']}\n";

        if (isset($task['dimensions'])) {
            if (isset($task['dimensions']['bac'])) {
                $pickupComments .= "{$task['dimensions']['bac']} × bac(s)\n";
            }
            if (isset($task['dimensions']['weight'])) {
                $pickupComments .= "{$task['dimensions']['weight']} kg\n";

                $delivery->setWeight(
                    intval($task['dimensions']['weight'] * 1000)
                );
            }
        }

        $dropoffComments = '';

        $buildingInfo = isset($task['contact']) && isset($task['contact']['buildingInfo']) ?
            $task['contact']['buildingInfo'] : [];

        if (isset($buildingInfo['digicode1']) && !empty($buildingInfo['digicode1'])) {
            $dropoffComments .= "Digicode : {$buildingInfo['digicode1']}\n";
        }

        if (isset($buildingInfo['floor']) && !empty($buildingInfo['floor'])) {
            $dropoffComments .= "Étage : {$buildingInfo['floor']}\n";
        }

        if (isset($buildingInfo['hasInterphone']) && true === $buildingInfo['hasInterphone'] &&
            isset($buildingInfo['interphoneCode']) && !empty($buildingInfo['interphoneCode'])) {
            $dropoffComments .= "Code interphone : {$buildingInfo['interphoneCode']}\n";
        }

        $delivery->getPickup()->setComments($pickupComments);

        if (!empty($dropoffComments)) {
            $delivery->getDropoff()->setComments($dropoffComments);
        }

        // IMPORTANT
        // This is what will be used to set the external tracking id
        $delivery->getDropoff()->setRef($task['id']);

        $store = $this->resolveStore($task);

        $store->addDelivery($delivery);

        // Call DeliveryManager::setDefaults here,
        // once the store has been associated
        $this->deliveryManager->setDefaults($delivery);

        return $delivery;
    }

    private function onTaskChanged(array $task): ?Delivery
    {
        // Bail early
        if (!in_array($task['progress'], self::$onTaskChangedEvents)) {
            return null;
        }

        if (!isset($task['extTrackId'])) {
            $this->logger->error(sprintf('Task "%s" has no "extTrackId" property', $task['_id']));
            return null;
        }

        $extTrackId = $task['extTrackId'];

        $delivery = $this->deliveryRepository->findOneByHashId($extTrackId);

        if (!$delivery) {
            $this->logger->error(sprintf('Could not find delivery corresponding to hash "%s"', $extTrackId));
            return null;
        }

        if ('DISCARDED' === $task['progress']) {

            foreach ($delivery->getTasks() as $task) {
                $this->taskManager->cancel($task);
            }

            return $delivery;
        }

        return null;
    }

    private function resolveStore(array $task)
    {
        $this->logger->info(
            sprintf('Looking for a store for hub "%s"', $task['hub'])
        );

        $hub = $this->entityManager
            ->getRepository(UrbantzHub::class)
            ->findOneBy(['hub' => $task['hub']]);

        if (null !== $hub) {

            $this->logger->info(
                sprintf('Found store "%s" for hub "%s"', $hub->getStore()->getName(), $task['hub'])
            );

            return $hub->getStore();
        }

        $this->logger->info(
            sprintf('No store found for hub "%s", resolving store from token', $task['hub'])
        );

        return $this->storeExtractor->extractStore();
    }
}
