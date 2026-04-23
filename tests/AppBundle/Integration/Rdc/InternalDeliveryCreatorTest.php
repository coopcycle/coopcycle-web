<?php

namespace Tests\AppBundle\Integration\Rdc;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Integration\Rdc\Coopcycle\InternalDeliveryCreator;
use AppBundle\Integration\Rdc\DTO\ServiceRequest;
use AppBundle\Integration\Rdc\DTO\ServiceRequestAddress;
use AppBundle\Integration\Rdc\DTO\ServiceRequestContact;
use AppBundle\Integration\Rdc\DTO\TimeSlot;
use AppBundle\Service\DeliveryOrderManager;
use AppBundle\Sylius\Order\OrderInterface;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

class InternalDeliveryCreatorTest extends TestCase
{
    use ProphecyTrait;

    private EntityManagerInterface $entityManager;
    private DeliveryOrderManager $deliveryOrderManager;
    private LoggerInterface $logger;
    private InternalDeliveryCreator $creator;

    protected function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class)->reveal();
        $this->deliveryOrderManager = $this->prophesize(DeliveryOrderManager::class)->reveal();
        $this->logger = $this->prophesize(LoggerInterface::class)->reveal();

        $this->creator = new InternalDeliveryCreator(
            $this->entityManager,
            $this->deliveryOrderManager,
            $this->logger
        );
    }

    public function testCreateDeliveryBasic(): void
    {
        $orderData = new ServiceRequest(
            addresses: [
                'pickup' => new ServiceRequestAddress(
                    streetAddress: '123 Pickup St',
                    city: 'Paris',
                    postalCode: '75001',
                    country: 'FR',
                ),
                'dropoff' => new ServiceRequestAddress(
                    streetAddress: '456 Dropoff Ave',
                    city: 'Lyon',
                    postalCode: '69001',
                    country: 'FR',
                ),
            ],
            externalRef: 'EXT-001',
            barcode: 'BC-001',
            contractRef: 'CONTRACT-001',
        );

        $store = $this->prophesize(Store::class)->reveal();

        $delivery = $this->creator->createDelivery($orderData, $store, 'lo://test-uri');

        $this->assertInstanceOf(Delivery::class, $delivery);
        $this->assertSame($store, $delivery->getStore());

        $pickup = $delivery->getPickup();
        $this->assertInstanceOf(\AppBundle\Entity\Task::class, $pickup);
        $this->assertNotNull($pickup->getAddress());
        $this->assertEquals('123 Pickup St', $pickup->getAddress()->getStreetAddress());
        $this->assertEquals('Paris', $pickup->getAddress()->getAddressLocality());

        $dropoff = $delivery->getDropoff();
        $this->assertInstanceOf(\AppBundle\Entity\Task::class, $dropoff);
        $this->assertNotNull($dropoff->getAddress());
        $this->assertEquals('456 Dropoff Ave', $dropoff->getAddress()->getStreetAddress());
        $this->assertEquals('Lyon', $dropoff->getAddress()->getAddressLocality());

        $this->assertSame($dropoff, $pickup->getNext());
        $this->assertSame($pickup, $dropoff->getPrevious());
    }

    public function testCreateDeliveryWithOrder(): void
    {
        $orderData = new ServiceRequest(
            addresses: [
                'pickup' => new ServiceRequestAddress(
                    streetAddress: '10 Rue Jean Jaurès',
                    city: 'Marseille',
                    postalCode: '13001',
                    country: 'FR',
                ),
                'dropoff' => new ServiceRequestAddress(
                    streetAddress: '20 Avenue Charles de Gaulle',
                    city: 'Nice',
                    postalCode: '06000',
                    country: 'FR',
                ),
            ],
            externalRef: 'EXT-002',
            barcode: 'BC-002',
            contractRef: 'CONTRACT-002',
        );

        $store = $this->prophesize(Store::class)->reveal();
        $order = $this->prophesize(OrderInterface::class)->reveal();

        $deliveryOrderManagerMock = $this->getMockBuilder(DeliveryOrderManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $deliveryOrderManagerMock->method('createOrder')->willReturn($order);

        $creator = new InternalDeliveryCreator(
            $this->entityManager,
            $deliveryOrderManagerMock,
            $this->logger
        );

        $delivery = $creator->createDeliveryWithOrder($orderData, $store, 'lo://test-uri-2');

        $this->assertInstanceOf(Delivery::class, $delivery);
    }

    public function testCreateDeliverySetsMetadata(): void
    {
        $loUri = 'lo://rdc-service-request/123';

        $orderData = new ServiceRequest(
            addresses: [
                'pickup' => new ServiceRequestAddress(
                    streetAddress: '50 Rue de la République',
                    city: 'Toulouse',
                    postalCode: '31000',
                    country: 'FR',
                ),
                'dropoff' => new ServiceRequestAddress(
                    streetAddress: '60 Boulevard de la Victoire',
                    city: 'Bordeaux',
                    postalCode: '33000',
                    country: 'FR',
                ),
            ],
            externalRef: 'EXT-003',
            barcode: 'BC-003',
            contractRef: 'CONTRACT-003',
        );

        $store = $this->prophesize(Store::class)->reveal();

        $delivery = $this->creator->createDelivery($orderData, $store, $loUri);

        $pickup = $delivery->getPickup();
        $metadata = $pickup->getMetadata();

        $this->assertArrayHasKey('rdc_lo_uri', $metadata);
        $this->assertArrayHasKey('rdc_external_ref', $metadata);
        $this->assertArrayHasKey('rdc_barcode', $metadata);
        $this->assertArrayHasKey('rdc_contract_ref', $metadata);
        $this->assertArrayHasKey('rdc_created_at', $metadata);

        $this->assertEquals($loUri, $metadata['rdc_lo_uri']);
        $this->assertEquals('EXT-003', $metadata['rdc_external_ref']);
        $this->assertEquals('BC-003', $metadata['rdc_barcode']);
        $this->assertEquals('CONTRACT-003', $metadata['rdc_contract_ref']);
    }

    public function testCreateDeliveryWithRecipient(): void
    {
        $orderData = new ServiceRequest(
            addresses: [
                'pickup' => new ServiceRequestAddress(
                    streetAddress: '100 Rue du Faubourg',
                    city: 'Nantes',
                    postalCode: '44000',
                    country: 'FR',
                ),
                'dropoff' => new ServiceRequestAddress(
                    streetAddress: '200 Avenue de la Grande Armée',
                    city: 'Lille',
                    postalCode: '59000',
                    country: 'FR',
                ),
            ],
            contacts: [
                'RECIPIENT' => new ServiceRequestContact(
                    name: 'Jean Dupont',
                    phone: '+33612345678',
                    email: 'jean.dupont@example.com',
                ),
            ],
            externalRef: 'EXT-004',
            barcode: 'BC-004',
            contractRef: 'CONTRACT-004',
        );

        $store = $this->prophesize(Store::class)->reveal();

        $delivery = $this->creator->createDelivery($orderData, $store, 'lo://test-uri-4');

        $dropoff = $delivery->getDropoff();

        $expectedComments = 'Recipient: Jean Dupont, Phone: +33612345678, Email: jean.dupont@example.com';
        $this->assertEquals($expectedComments, $dropoff->getComments());
    }

    public function testCreateDeliveryWithoutAddress(): void
    {
        $orderData = new ServiceRequest(
            addresses: [
                'pickup' => new ServiceRequestAddress(),
                'dropoff' => new ServiceRequestAddress(),
            ],
            externalRef: 'EXT-005',
            barcode: 'BC-005',
            contractRef: 'CONTRACT-005',
        );

        $store = $this->prophesize(Store::class)->reveal();

        $delivery = $this->creator->createDelivery($orderData, $store, 'lo://test-uri-5');

        $this->assertInstanceOf(Delivery::class, $delivery);

        $pickup = $delivery->getPickup();
        $this->assertNull($pickup->getAddress());

        $dropoff = $delivery->getDropoff();
        $this->assertNull($dropoff->getAddress());

        $metadata = $pickup->getMetadata();
        $this->assertEquals('lo://test-uri-5', $metadata['rdc_lo_uri']);
        $this->assertEquals('EXT-005', $metadata['rdc_external_ref']);
    }

    public function testCreateDeliveryWithTimeSlots(): void
    {
        $slotStart = new DateTimeImmutable('2026-04-23 10:00:00');
        $slotEnd = new DateTimeImmutable('2026-04-23 12:00:00');

        $orderData = new ServiceRequest(
            addresses: [
                'pickup' => new ServiceRequestAddress(
                    streetAddress: '300 Rue du Pont',
                    city: 'Strasbourg',
                    postalCode: '67000',
                    country: 'FR',
                ),
                'dropoff' => new ServiceRequestAddress(
                    streetAddress: '400 Rue du Canal',
                    city: 'Rouen',
                    postalCode: '76000',
                    country: 'FR',
                ),
            ],
            timeSlots: [
                'pickup' => new TimeSlot(start: $slotStart, end: $slotEnd),
                'dropoff' => new TimeSlot(start: $slotStart, end: $slotEnd),
            ],
            externalRef: 'EXT-006',
            barcode: 'BC-006',
            contractRef: 'CONTRACT-006',
        );

        $store = $this->prophesize(Store::class)->reveal();

        $delivery = $this->creator->createDelivery($orderData, $store, 'lo://test-uri-6');

        $pickup = $delivery->getPickup();

        $expectedDoneAfter = DateTime::createFromImmutable($slotStart->modify('-30 minutes'));
        $expectedDoneBefore = DateTime::createFromImmutable($slotEnd->modify('+30 minutes'));

        $this->assertEquals($expectedDoneAfter->format('Y-m-d H:i:s'), $pickup->getDoneAfter()->format('Y-m-d H:i:s'));
        $this->assertEquals($expectedDoneBefore->format('Y-m-d H:i:s'), $pickup->getDoneBefore()->format('Y-m-d H:i:s'));

        $dropoff = $delivery->getDropoff();

        $this->assertEquals($expectedDoneAfter->format('Y-m-d H:i:s'), $dropoff->getDoneAfter()->format('Y-m-d H:i:s'));
        $this->assertEquals($expectedDoneBefore->format('Y-m-d H:i:s'), $dropoff->getDoneBefore()->format('Y-m-d H:i:s'));
    }

    /**
     * Test end-to-end delivery creation with ServiceRequest DTO populated from real SPEC data.
     * Uses actual address structure with addressLines, multiple contacts (CUSTOMER_DISPATCH),
     * external references (REQUESTOR_ID, PROVIDER_ID), and nested time ranges from spec.
     */
    public function testCreateDeliveryWithRealSpecServiceRequest(): void
    {
        // Create ServiceRequest DTO with data from SPEC lines 626-785
        $orderData = new ServiceRequest(
            addresses: [
                'pickup' => new ServiceRequestAddress(
                    streetAddress: '16 Bd sde l\'industrie ZI D',
                    postalCode: '49000',
                    city: 'ECOUFLANT',
                    country: 'FR',
                ),
                'dropoff' => new ServiceRequestAddress(
                    streetAddress: '21 rue Jean Predali',
                    postalCode: '49100',
                    city: 'ANGERS',
                    country: 'FR',
                ),
            ],
            timeSlots: [
                'pickup' => new TimeSlot(
                    start: new DateTimeImmutable('2025-12-26T07:00:00.000Z'),
                    end: new DateTimeImmutable('2025-12-26T08:00:00.000Z'),
                ),
                'dropoff' => new TimeSlot(
                    start: new DateTimeImmutable('2025-12-26T09:00:00.000Z'),
                    end: new DateTimeImmutable('2025-12-26T09:45:59.999Z'),
                ),
            ],
            contacts: [
                'CUSTOMER_DISPATCH' => new ServiceRequestContact(
                    name: '',
                    phone: '03.04.05.06.07',
                    email: '',
                ),
            ],
            externalRef: 'C145237/1',
            barcode: null,
            contractRef: '45687G/1.12',
        );

        $store = $this->prophesize(Store::class)->reveal();

        $loUri = 'http://localhost:8080/services/ec1697f082';
        $delivery = $this->creator->createDelivery($orderData, $store, $loUri);

        $this->assertInstanceOf(Delivery::class, $delivery);
        $this->assertSame($store, $delivery->getStore());

        // Verify pickup task with real spec address data
        $pickup = $delivery->getPickup();
        $this->assertInstanceOf(\AppBundle\Entity\Task::class, $pickup);
        $this->assertNotNull($pickup->getAddress());
        $this->assertEquals("16 Bd sde l'industrie ZI D", $pickup->getAddress()->getStreetAddress());
        $this->assertEquals('49000', $pickup->getAddress()->getPostalCode());
        $this->assertEquals('ECOUFLANT', $pickup->getAddress()->getAddressLocality());
        $this->assertEquals('FR', $pickup->getAddress()->getAddressCountry());

        // Verify pickup time window from spec
        $this->assertNotNull($pickup->getDoneAfter());
        $this->assertNotNull($pickup->getDoneBefore());

        // Verify dropoff task with real spec address data
        $dropoff = $delivery->getDropoff();
        $this->assertInstanceOf(\AppBundle\Entity\Task::class, $dropoff);
        $this->assertNotNull($dropoff->getAddress());
        $this->assertEquals('21 rue Jean Predali', $dropoff->getAddress()->getStreetAddress());
        $this->assertEquals('49100', $dropoff->getAddress()->getPostalCode());
        $this->assertEquals('ANGERS', $dropoff->getAddress()->getAddressLocality());
        $this->assertEquals('FR', $dropoff->getAddress()->getAddressCountry());

        // Verify dropoff time window from spec
        $this->assertNotNull($dropoff->getDoneAfter());
        $this->assertNotNull($dropoff->getDoneBefore());

        // Verify task chain
        $this->assertSame($dropoff, $pickup->getNext());
        $this->assertSame($pickup, $dropoff->getPrevious());

        // Verify metadata with real spec data
        $metadata = $pickup->getMetadata();
        $this->assertArrayHasKey('rdc_lo_uri', $metadata);
        $this->assertArrayHasKey('rdc_external_ref', $metadata);
        $this->assertArrayHasKey('rdc_contract_ref', $metadata);
        $this->assertArrayHasKey('rdc_created_at', $metadata);

        $this->assertEquals($loUri, $metadata['rdc_lo_uri']);
        $this->assertEquals('C145237/1', $metadata['rdc_external_ref']);
        $this->assertEquals('45687G/1.12', $metadata['rdc_contract_ref']);
    }
}