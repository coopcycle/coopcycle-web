<?php

namespace AppBundle\Action\Incident;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Put;
use AppBundle\Action\Base;
use AppBundle\Api\Dto\DeliveryInputDto;
use AppBundle\Api\Dto\DeliveryOrderDto;
use AppBundle\Api\State\DeliveryCreateOrUpdateProcessor;
use AppBundle\Domain\Order\Event\OrderPriceUpdated;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Incident\IncidentEvent;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\CalculateUsingPricingRules;
use AppBundle\Entity\Task;
use AppBundle\Pricing\PricingManager;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\TaskManager;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Taxation\TaxesHelper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class IncidentAction extends Base
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TaskManager $taskManager,
        private readonly AdjustmentFactoryInterface $adjustmentFactory,
        private readonly OrderProcessorInterface $orderProcessor,
        private readonly TranslatorInterface $translator,
        private readonly DenormalizerInterface $denormalizer,
        private readonly DeliveryCreateOrUpdateProcessor $deliveryProcessor,
        private readonly MessageBusInterface $eventBus,
        private readonly TaxesHelper $taxesHelper,
        private readonly IriConverterInterface $iriConverter,
        private readonly DeliveryManager $deliveryManager,
        private readonly PricingManager $pricingManager,
    )
    {
    }

    public function __invoke(Incident $data, UserInterface $user, Request $request): Incident
    {

        $params = $this->parseRequest($request);
        $action = $params->get("action", null);

        $allowedActions = [
            IncidentEvent::TYPE_RESCHEDULE,
            IncidentEvent::TYPE_APPLY_PRICE_DIFF,
            IncidentEvent::TYPE_CANCEL_TASK,
            IncidentEvent::TYPE_TRANSPORTER_REPORT,
            IncidentEvent::TYPE_ACCEPT_SUGGESTION,
            IncidentEvent::TYPE_REJECT_SUGGESTION,
            IncidentEvent::TYPE_UPDATE_ADDRESS,
        ];

        if (empty($action)) {
            throw new \InvalidArgumentException("Action cannot be empty");
        }

        if (!in_array($action, $allowedActions)) {
            throw new \InvalidArgumentException(sprintf("Action %s is not supported", $action));
        }

        $event = new IncidentEvent();
        $event->setIncident($data);
        $event->setCreatedBy($user);

        switch ($action) {
            case IncidentEvent::TYPE_RESCHEDULE:
                $this->reschedule($data, $event, $params);
                break;
            case IncidentEvent::TYPE_APPLY_PRICE_DIFF:
                $this->applyPriceDiff($data, $event, $params);
                break;
            case IncidentEvent::TYPE_CANCEL_TASK:
                $this->cancelTask($data, $event);
                break;
            case IncidentEvent::TYPE_TRANSPORTER_REPORT:
                $this->createTransporterReport($data, $event, $params);
                break;
            case IncidentEvent::TYPE_ACCEPT_SUGGESTION:
                $this->acceptSuggestion($data, $event, $params);
                break;
            case IncidentEvent::TYPE_REJECT_SUGGESTION:
                $this->rejectSuggestion($data, $event, $params);
                break;
            case IncidentEvent::TYPE_UPDATE_ADDRESS:
                $this->updateAddress($data, $event, $params);
                break;
        }

        $data->addEvent($event);

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;

    }

    private function reschedule(Incident &$data, IncidentEvent &$event, InputBag $params): void
    {
        $after = $params->get("after", null);
        $before = $params->get("before", null);

        if (is_null($after) || is_null($before)) {
            throw new \InvalidArgumentException("After and before dates are required");
        }

        $task = $data->getTask();
        $rescheduledAfter = new \DateTime($after);
        $rescheduledBefore = new \DateTime($before);


        $event->setType(IncidentEvent::TYPE_RESCHEDULE);
        $event->setMetadata([
            'from' => [
                'after' => $task->getAfter()->format(DateTime::ISO8601),
                'before' => $task->getBefore()->format(DateTime::ISO8601)
            ],
            'to' => [
                'after' => $rescheduledAfter->format(DateTime::ISO8601),
                'before' => $rescheduledBefore->format(DateTime::ISO8601)
            ]
        ]);

        $this->taskManager->reschedule($task, $rescheduledAfter, $rescheduledBefore);

        if ($params->getBoolean('close')) {
            $data->setStatus(Incident::STATUS_CLOSED);
        }
    }

    private function updateAddress(Incident &$data, IncidentEvent &$event, InputBag $params): void
    {
        if ($data->getFailureReasonCode() !== 'ADDRESS_REVIEW_NEEDED') {
            throw new \InvalidArgumentException("Address can only be updated on an ADDRESS_REVIEW_NEEDED incident");
        }

        // Either the IRI of an address from the store address book, or a geocoded location
        $newAddress = $params->all()['address'] ?? null;

        $task = $data->getTask();
        $oldAddress = $task->getAddress();

        $address = is_string($newAddress)
            ? $this->addressFromStore($task, $newAddress)
            : $this->addressFromLocation($oldAddress, $newAddress);

        $task->setAddress($address);

        $event->setType(IncidentEvent::TYPE_UPDATE_ADDRESS);
        $event->setMetadata([
            'address' => [
                'from' => $oldAddress->getStreetAddress(),
                'to' => $address->getStreetAddress(),
            ],
            'price' => $this->recalculatePrice($task),
        ]);

        if ($params->getBoolean('close')) {
            $data->setStatus(Incident::STATUS_CLOSED);
        }
    }

    private function addressFromStore(Task $task, string $iri): Address
    {
        /** @var ?Store $store */
        $store = $task->getDelivery()?->getStore();
        if (is_null($store)) {
            throw new \InvalidArgumentException("There is no store linked to this task");
        }

        $address = $this->iriConverter->getResourceFromIri($iri);
        if (!$address instanceof Address || !$store->getAddresses()->contains($address)) {
            throw new \InvalidArgumentException(sprintf("Address %s does not belong to the store", $iri));
        }

        // The address book entry is shared, the task gets a copy
        return $address->clone();
    }

    private function addressFromLocation(Address $oldAddress, ?array $location): Address
    {
        if (empty($location['streetAddress']) || !isset($location['latitude'], $location['longitude'])) {
            throw new \InvalidArgumentException("address.streetAddress, address.latitude and address.longitude are required");
        }

        // The address may be shared (i.e store address book), so the task gets a copy.
        // Keeps the contact details, only the location is wrong.
        $address = $oldAddress->clone();
        $address->setStreetAddress($location['streetAddress']);
        $address->setPostalCode($location['postalCode'] ?? null);
        $address->setAddressLocality($location['addressLocality'] ?? null);
        $address->setGeo(new GeoCoordinates($location['latitude'], $location['longitude']));

        return $address;
    }

    /**
     * Same rules as when a task is cancelled (see ProcessOrderAfterTaskCancellationHandler),
     * but synchronously, so that the dispatcher sees the new price once the page reloads.
     *
     * @return array<string,int>|null The price before and after, null when the price was kept
     */
    private function recalculatePrice(Task $task): ?array
    {
        $delivery = $task->getDelivery();
        if (is_null($delivery)) {
            return null;
        }

        // The distance depends on the address
        $this->deliveryManager->calculateRoute($delivery);

        $order = $delivery->getOrder();
        if (is_null($order) || $order->isFoodtech() || is_null($delivery->getStore())) {
            return null;
        }

        try {
            $deliveryPrice = $order->getDeliveryPrice();
        } catch (\Exception $e) {
            return null;
        }

        // A price set by hand is kept as is
        if ($deliveryPrice instanceof ArbitraryPrice) {
            return null;
        }

        $oldTotal = $order->getTotal();
        $oldTaxTotal = $order->getTaxTotal();

        $productVariants = $this->pricingManager->getProductVariantsWithPricingStrategy(
            $delivery,
            new CalculateUsingPricingRules($order->getManualSupplements())
        );
        $this->pricingManager->processDeliveryOrder($order, $productVariants);

        if ($oldTotal !== $order->getTotal() || $oldTaxTotal !== $order->getTaxTotal()) {
            $this->eventBus->dispatch(new OrderPriceUpdated($order,
                $order->getTotal(),
                $order->getTaxTotal(),
                $oldTotal,
                $oldTaxTotal
            ));
        }

        $this->entityManager->persist($order);

        $diff = $order->getTotal() - $oldTotal;
        $diffTax = $order->getTaxTotal() - $oldTaxTotal;

        return [
            'from' => $oldTotal,
            'to' => $order->getTotal(),
            'diff_tax_included' => $diff,
            'diff_tax_excluded' => $diff - $diffTax,
        ];
    }

    private function cancelTask(Incident &$data, IncidentEvent &$event): void
    {
        $task = $data->getTask();
        $this->taskManager->cancel($task, recalculatePrice: true);
        $event->setType(IncidentEvent::TYPE_CANCEL_TASK);
    }

    private function applyPriceDiff(Incident &$data, IncidentEvent &$event, InputBag $params): void
    {
        $priceDiff = $params->get("diff", null);

        if (is_null($priceDiff)) {
            throw new \InvalidArgumentException("diff is required");
        }

        // Amounts are in cents, but may come as floats from JavaScript (e.g. 12.34 * 100)
        $priceDiff = (int) round((float) $priceDiff);

        $order = $data->getTask()->getDelivery()?->getOrder();
        if (is_null($order)) {
            throw new \InvalidArgumentException("There is no order linked to this task");
        }

        $oldTotal = $order->getTotal();
        $oldTaxTotal = $order->getTaxTotal();
        $oldIncidentTaxTotal = $order->getIncidentTaxTotal();

        if ($order->getTotal() + $priceDiff < 0) {
            throw new \InvalidArgumentException("Price diff cannot be negative");
        }

        $adjustment = $this->adjustmentFactory->createWithData(
            AdjustmentInterface::INCIDENT_ADJUSTMENT,
            $this->translator->trans('order.adjustment_type.incident'),
            $priceDiff);
        $order->addAdjustment($adjustment);

        $this->orderProcessor->process($order);

        $priceUpdatedEvent = new OrderPriceUpdated($order,
            $order->getTotal(),
            $order->getTaxTotal(),
            $oldTotal,
            $oldTaxTotal
        );
        $this->eventBus->dispatch($priceUpdatedEvent);

        $this->entityManager->persist($order);

        // Whether the diff includes tax depends on the service tax rate,
        // so store both amounts to display them without knowing the rate.
        $diffTax = $order->getIncidentTaxTotal() - $oldIncidentTaxTotal;
        $taxIncluded = $this->taxesHelper->getServiceTaxRate()?->isIncludedInPrice() ?? true;

        $event->setType(IncidentEvent::TYPE_APPLY_PRICE_DIFF);
        $event->setMetadata([
            "diff" => $priceDiff,
            "diff_tax_excluded" => $taxIncluded ? $priceDiff - $diffTax : $priceDiff,
            "diff_tax_included" => $taxIncluded ? $priceDiff : $priceDiff + $diffTax,
        ]);
    }

    private function createTransporterReport(Incident &$data, IncidentEvent &$event, InputBag $params): void
    {
        /** @var ?Store $store */
        $store = $data->getTask()->getDelivery()?->getStore();

        if (is_null($store)) {
            throw new \InvalidArgumentException("There is no store linked to this task");
        }

        //TODO(multitrans): Add support for multi transporters
        if (!$store->isTransporterEnabled()) {
            throw new \InvalidArgumentException("Transporter report cannot be created for store without DBSchenker");
        }

        $failureReason = $params->get("failure_reason", null);
        if (is_null($failureReason)) {
            throw new \InvalidArgumentException("failure_reason is required");
        }

        $createdAt = $params->get("created_at", null);
        if (is_null($createdAt)) {
            throw new \InvalidArgumentException("created_at is required");
        }
        $createdAt = new \DateTime($createdAt);

        /** @var array $pods */
        $pods = $params->all("pods");

        $appointment = $params->get("appointment", null);

        $task = $data->getTask();
        $importEDI = $task->getImportMessage();
        if (is_null($importEDI)) {
            throw new \InvalidArgumentException("There is no import message linked to this task");
        }

        $ediMessage = EDIFACTMessage::createReport($importEDI, $failureReason);
        $ediMessage->setCreatedAt($createdAt);
        $ediMessage->setPods($pods);

        if (!is_null($appointment)) {
            $ediMessage->setAppointment(new \DateTime($appointment));
        }

        $task->addEdifactMessage($ediMessage);

        $this->entityManager->persist($ediMessage);
        $this->entityManager->persist($task);


        $event->setType(IncidentEvent::TYPE_TRANSPORTER_REPORT);

    }

    private function acceptSuggestion(Incident &$data, IncidentEvent &$event, InputBag $params): void
    {
        $priceDiff = $params->get("diff", null);

        $metadata = $data->getMetadata();

        $suggestionMetadata = array_values(array_filter($metadata, function ($item) {
            return isset($item['suggestion']);
        }));

        /** @var DeliveryInputDto $deliveryData */
        $deliveryData = $this->denormalizer->denormalize(
            $suggestionMetadata[0]['suggestion'],
            DeliveryInputDto::class,
            'json'
        );

        if (null === $deliveryData->order) {
            $deliveryData->order = new DeliveryOrderDto();
        }

        // Force price recalculation
        $deliveryData->order->recalculatePrice = true;

        $this->deliveryProcessor->process($deliveryData, new Put(), ['id' => $deliveryData->id]);

        $event->setType(IncidentEvent::TYPE_ACCEPT_SUGGESTION);
        $event->setMetadata(["diff" => $priceDiff]);
    }

    private function rejectSuggestion(Incident &$data, IncidentEvent &$event, InputBag $params): void
    {
        $priceDiff = $params->get("diff", null);

        $event->setType(IncidentEvent::TYPE_REJECT_SUGGESTION);
        //Save price diff even if suggestion is rejected
        $event->setMetadata(["diff" => $priceDiff]);
    }
}
