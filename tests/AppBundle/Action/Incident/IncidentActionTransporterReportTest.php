<?php

namespace Tests\AppBundle\Action\Incident;

use AppBundle\Action\Incident\IncidentAction;
use AppBundle\Api\State\DeliveryCreateOrUpdateProcessor;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Incident\IncidentEvent;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The "Send report to the transporter" modal on the incident page, which is the
 * only way an incident ever reaches the transporter: nothing in the courier
 * flow emits an EDIFACT message.
 */
class IncidentActionTransporterReportTest extends TestCase
{
    use ProphecyTrait;

    const POD_URL = 'https://demo.coopcycle.org/media/incidents/images/damaged-parcel.jpg';

    private function initAction(EntityManagerInterface $entityManager): IncidentAction
    {
        return new IncidentAction(
            $entityManager,
            $this->prophesize(TaskManager::class)->reveal(),
            $this->prophesize(AdjustmentFactoryInterface::class)->reveal(),
            $this->prophesize(OrderProcessorInterface::class)->reveal(),
            $this->prophesize(TranslatorInterface::class)->reveal(),
            $this->prophesize(DenormalizerInterface::class)->reveal(),
            $this->prophesize(DeliveryCreateOrUpdateProcessor::class)->reveal(),
            $this->prophesize(MessageBusInterface::class)->reveal(),
        );
    }

    private function initRequest(array $payload): Request
    {
        return Request::create('/api/incidents/1/action', 'PUT', content: json_encode(
            array_merge(['action' => IncidentEvent::TYPE_TRANSPORTER_REPORT], $payload)
        ));
    }

    public function testTransporterReportAttachesPodsToTheEdifactMessage(): void
    {
        $store = new Store();
        $store->setTransporter('DBSCHENKER');

        $importMessage = new EDIFACTMessage();
        $importMessage->setMessageType(EDIFACTMessage::MESSAGE_TYPE_SCONTR);
        $importMessage->setDirection(EDIFACTMessage::DIRECTION_INBOUND);
        $importMessage->setTransporter('DBSCHENKER');
        $importMessage->setReference('JOY0123456789');

        $task = new Task();
        $task->setType(Task::TYPE_DROPOFF);
        $task->addEdifactMessage($importMessage);

        $delivery = new Delivery();
        $delivery->setStore($store);
        $task->setDelivery($delivery);

        $incident = new Incident();
        $incident->setTask($task);

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $action = $this->initAction($entityManager->reveal());

        $action(
            $incident,
            $this->prophesize(User::class)->reveal(),
            $this->initRequest([
                'failure_reason' => 'RST|DAF',
                'created_at' => '2026-09-17T10:15:00+02:00',
                'pods' => [self::POD_URL],
            ])
        );

        $reports = $task->getReports();
        $this->assertCount(1, $reports);

        /** @var EDIFACTMessage $report */
        $report = $reports->first();
        $this->assertEquals([self::POD_URL], $report->getPods());
        $this->assertEquals('RST|DAF', $report->getSubMessageType());
        $this->assertEquals(EDIFACTMessage::DIRECTION_OUTBOUND, $report->getDirection());
        $this->assertEquals('DBSCHENKER', $report->getTransporter());
        $this->assertEquals('JOY0123456789', $report->getReference());

        // The operator picks when the event happened; it becomes the DTM+DSJ of
        // the report, so it must survive the Gedmo timestampable listener.
        $this->assertEquals(
            '2026-09-17 10:15:00',
            $report->getCreatedAt()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d H:i:s')
        );

        $this->assertEquals(
            IncidentEvent::TYPE_TRANSPORTER_REPORT,
            $incident->getEvents()->first()->getType()
        );
    }

    public function testTransporterReportKeepsTheAppointment(): void
    {
        $store = new Store();
        $store->setTransporter('DBSCHENKER');

        $importMessage = new EDIFACTMessage();
        $importMessage->setMessageType(EDIFACTMessage::MESSAGE_TYPE_SCONTR);
        $importMessage->setDirection(EDIFACTMessage::DIRECTION_INBOUND);
        $importMessage->setTransporter('DBSCHENKER');
        $importMessage->setReference('JOY0123456789');

        $task = new Task();
        $task->setType(Task::TYPE_DROPOFF);
        $task->addEdifactMessage($importMessage);

        $delivery = new Delivery();
        $delivery->setStore($store);
        $task->setDelivery($delivery);

        $incident = new Incident();
        $incident->setTask($task);

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $action = $this->initAction($entityManager->reveal());

        $action(
            $incident,
            $this->prophesize(User::class)->reveal(),
            $this->initRequest([
                'failure_reason' => 'RST|PVI',
                'created_at' => '2026-09-17T10:15:00+02:00',
                'appointment' => '2026-09-18T14:00:00+02:00',
                'pods' => [],
            ])
        );

        /** @var EDIFACTMessage $report */
        $report = $task->getReports()->first();
        $this->assertEquals(
            '2026-09-18 14:00:00',
            $report->getAppointment()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d H:i:s')
        );
        $this->assertEmpty($report->getPods());
    }

    public function testTransporterReportWithoutStore(): void
    {
        $task = new Task();
        $task->setType(Task::TYPE_DROPOFF);

        $incident = new Incident();
        $incident->setTask($task);

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $action = $this->initAction($entityManager->reveal());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('There is no store linked to this task');

        $action(
            $incident,
            $this->prophesize(User::class)->reveal(),
            $this->initRequest([
                'failure_reason' => 'RST|DAF',
                'created_at' => '2026-09-17T10:15:00+02:00',
                'pods' => [self::POD_URL],
            ])
        );
    }

    public function testTransporterReportOnStoreWithoutTransporter(): void
    {
        $store = new Store();

        $task = new Task();
        $task->setType(Task::TYPE_DROPOFF);

        $delivery = new Delivery();
        $delivery->setStore($store);
        $task->setDelivery($delivery);

        $incident = new Incident();
        $incident->setTask($task);

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $action = $this->initAction($entityManager->reveal());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Transporter report cannot be created for store without DBSchenker');

        $action(
            $incident,
            $this->prophesize(User::class)->reveal(),
            $this->initRequest([
                'failure_reason' => 'RST|DAF',
                'created_at' => '2026-09-17T10:15:00+02:00',
                'pods' => [self::POD_URL],
            ])
        );
    }

    public function testTransporterReportWithoutFailureReason(): void
    {
        $store = new Store();
        $store->setTransporter('DBSCHENKER');

        $task = new Task();
        $task->setType(Task::TYPE_DROPOFF);

        $delivery = new Delivery();
        $delivery->setStore($store);
        $task->setDelivery($delivery);

        $incident = new Incident();
        $incident->setTask($task);

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $action = $this->initAction($entityManager->reveal());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('failure_reason is required');

        $action(
            $incident,
            $this->prophesize(User::class)->reveal(),
            $this->initRequest([
                'created_at' => '2026-09-17T10:15:00+02:00',
                'pods' => [self::POD_URL],
            ])
        );
    }

    public function testTransporterReportWithoutCreatedAt(): void
    {
        $store = new Store();
        $store->setTransporter('DBSCHENKER');

        $task = new Task();
        $task->setType(Task::TYPE_DROPOFF);

        $delivery = new Delivery();
        $delivery->setStore($store);
        $task->setDelivery($delivery);

        $incident = new Incident();
        $incident->setTask($task);

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $action = $this->initAction($entityManager->reveal());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('created_at is required');

        $action(
            $incident,
            $this->prophesize(User::class)->reveal(),
            $this->initRequest([
                'failure_reason' => 'RST|DAF',
                'pods' => [self::POD_URL],
            ])
        );
    }

    public function testTransporterReportWithoutImportMessage(): void
    {
        $store = new Store();
        $store->setTransporter('DBSCHENKER');

        // A task of a transporter-enabled store, but not imported from one:
        // there is no reference to report against.
        $task = new Task();
        $task->setType(Task::TYPE_DROPOFF);

        $delivery = new Delivery();
        $delivery->setStore($store);
        $task->setDelivery($delivery);

        $incident = new Incident();
        $incident->setTask($task);

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $action = $this->initAction($entityManager->reveal());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('There is no import message linked to this task');

        $action(
            $incident,
            $this->prophesize(User::class)->reveal(),
            $this->initRequest([
                'failure_reason' => 'RST|DAF',
                'created_at' => '2026-09-17T10:15:00+02:00',
                'pods' => [self::POD_URL],
            ])
        );
    }
}
