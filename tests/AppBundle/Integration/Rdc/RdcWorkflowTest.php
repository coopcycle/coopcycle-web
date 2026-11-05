<?php

declare(strict_types=1);

namespace Tests\AppBundle\Integration\Rdc;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskImage;
use AppBundle\Service\Geocoder;
use AppBundle\Service\RoutingInterface;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * The whole RDC workflow, from the requestor's webhook to the BOL calls, with
 * the BOL nodes and their Keycloak played by FakeBol:
 *
 * - a service request becomes a delivery, a BOL service and its transport
 *   activity, then the requestor's service request is approved;
 * - a cancelled delivery cancels the service and the activity, then the
 *   requestor's service request;
 * - a delivered delivery finishes them with its proof of delivery.
 */
class RdcWorkflowTest extends WebTestCase
{
    private const CONNECTION = 'BOL.MEMBER.PROVIDER';
    private const OUR_NODE = 'https://bol-api.coopcycle.test';
    private const OUR_MEMBER = 'BOL.MEMBER.COOPCYCLE';
    private const SECRET = 'rdc-test-secret';

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private FakeBol $bol;
    private Store $store;
    private string $loUri;

    /** @var array<string, array{0: mixed, 1: mixed}> */
    private array $previousEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setEnv('RDC_ENABLED', '1');
        $this->setEnv('RDC_WEBHOOK_SECRET', self::SECRET);
        $this->setEnv('RDC_CONNECTIONS', (string) json_encode([
            self::CONNECTION => [
                'enabled' => true,
                'name' => 'Provider',
                'instanceBaseUrl' => self::OUR_NODE,
                'memberProvider' => 'COOPCYCLE',
                'keycloakBaseUrl' => 'https://keycloak.coopcycle.test',
                'keycloakRealm' => 'COOPCYCLE',
                'keycloakClientId' => 'COOPCYCLE',
                'keycloakClientSecret' => 'secret',
                'keycloakUsername' => 'coopcycle',
                'keycloakPassword' => 'password',
                'aclAuthorizations' => [['memberIdentifier' => self::CONNECTION, 'mode' => 'READ']],
            ],
        ]));

        $this->client = static::createClient();
        // Keep the same container, hence the same database transaction, between requests
        $this->client->disableReboot();

        $container = static::getContainer();

        $this->bol = new FakeBol();
        /** @var MockHttpClient $httpClient */
        $httpClient = $container->get('rdc_client_mock');
        $httpClient->setResponseFactory($this->bol);

        // No geocoding or routing service in the tests
        $geocoder = $this->createMock(Geocoder::class);
        $geocoder->method('geocode')->willReturnCallback(function (string $value) {
            $address = new Address();
            $address->setStreetAddress($value);
            $address->setGeo(new GeoCoordinates(45.764, 4.835));

            return $address;
        });
        $container->set(Geocoder::class, $geocoder);

        $routing = $this->createMock(RoutingInterface::class);
        $routing->method('getDistance')->willReturn(0);
        $routing->method('getDuration')->willReturn(0);
        $routing->method('getPolyline')->willReturn('');
        $container->set('routing_service', $routing);

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->beginTransaction();

        $this->store = new Store();
        $this->store->setName('RDC workflow');
        $this->store->setRdcConnectionId(self::CONNECTION);
        $this->entityManager->persist($this->store);
        $this->entityManager->flush();

        // Unique, so that the webhook idempotency claim of a previous run doesn't skip it
        $this->loUri = sprintf('https://bol-api.requestor.test/service-requests/%s', uniqid('sr-'));
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        foreach ($this->previousEnv as $name => [$server, $env]) {
            $this->restoreEnv($name, $server, $env);
        }

        parent::tearDown();
    }

    public function testAServiceRequestBecomesADeliveryAndABolService(): void
    {
        $delivery = $this->receiveServiceRequest();

        $serviceId = (string) $delivery->getId();
        $activityId = sprintf('%d.transport', $delivery->getPickup()->getId());
        $serviceUri = sprintf('%s/services/%s', self::OUR_NODE, $serviceId);

        // The delivery is in the store of the connection, and remembers the BOL objects
        $this->assertSame($this->store, $delivery->getStore());
        $this->assertSame($serviceId, $delivery->getMetadata()['rdc']['service_id']);
        $this->assertSame($activityId, $delivery->getMetadata()['rdc']['activity_id']);
        $this->assertSame('C-26-1234-1', $delivery->getExternalReference());

        // The duplicate event is skipped: one service, one activity
        $this->assertSame([
            'POST /services',
            'POST /activities',
            sprintf('PATCH /services/%s', $serviceId),
            sprintf('POST %s/change-requests', $this->loPath()),
        ], $this->bol->summary());

        // Every call is made as our member, with our own token
        foreach ($this->bol->calls() as $call) {
            $this->assertSame(['Authorization: Bearer bol-token'], $call['headers']['authorization']);
            $this->assertSame([sprintf('X-BOL-Member-Identifier: %s', self::OUR_MEMBER)], $call['headers']['x-bol-member-identifier']);
        }

        [$service, $activity, $link, $changeRequest] = $this->bol->calls();

        // The service, on our node, for the requestor
        $this->assertStringStartsWith(self::OUR_NODE, $service['url']);
        $this->assertSame(['source' => 'true', 'id' => $serviceId], $service['query']);
        $this->assertSame(['legalEntityName' => 'REQUESTOR'], $service['body']['requestor']);
        $this->assertSame(
            [sprintf('X-BOL-ACL-authorizations: %s', json_encode([['memberIdentifier' => self::CONNECTION, 'mode' => 'READ']]))],
            $service['headers']['x-bol-acl-authorizations']
        );

        // Its transport activity
        $this->assertSame($activityId, $activity['query']['id']);
        $this->assertSame(['uri' => $serviceUri], $activity['body']['relatedObject']);
        $this->assertSame('TRANSPORT', $activity['body']['activityType']);

        // The service references the activity
        $this->assertSame(
            [['uri' => sprintf('%s/activities/%s', self::OUR_NODE, $activityId)]],
            $link['body']['scheduledActivities']
        );

        // The requestor's service request is approved and linked to the service
        $this->assertSame(sprintf('%s/change-requests?source=true', $this->loUri), $changeRequest['url']);
        $this->assertSame(1, $changeRequest['body']['logisticsObjectRevision']);
        $this->assertSame(self::OUR_MEMBER, $changeRequest['body']['requestorMemberIdentifier']);
        $operations = $this->operationsByPath($changeRequest['body']);
        $this->assertSame('APPROVED', $operations['/responseStatus']);
        $this->assertSame(['uri' => $serviceUri], $operations['/service']);
    }

    public function testACancelledDeliveryCancelsTheServiceAndTheServiceRequest(): void
    {
        $delivery = $this->receiveServiceRequest();
        $serviceId = (string) $delivery->getId();
        $activityId = sprintf('%d.transport', $delivery->getPickup()->getId());
        $this->bol->forget();

        $this->taskManager()->cancel($delivery->getPickup());
        $this->entityManager->flush();

        $this->assertSame([
            sprintf('PATCH /services/%s', $serviceId),
            sprintf('PATCH /activities/%s', $activityId),
            sprintf('GET %s', $this->loPath()),
            sprintf('POST %s/change-requests', $this->loPath()),
        ], $this->bol->summary());

        [$service, $activity, $read, $changeRequest] = $this->bol->calls();

        $this->assertSame('CANCELLED', $service['body']['executionStatus']);
        $this->assertSame('CANCELLED', $activity['body']['executionStatus']);

        // The requestor's service request is cancelled at the revision just read
        $this->assertSame($this->loUri, $read['url']);
        $this->assertSame(sprintf('%s/change-requests?source=true', $this->loUri), $changeRequest['url']);
        $this->assertSame(FakeBol::REVISION, $changeRequest['body']['logisticsObjectRevision']);
        $this->assertSame(self::OUR_MEMBER, $changeRequest['body']['requestorMemberIdentifier']);
        $this->assertSame('CANCELLED', $this->operationsByPath($changeRequest['body'])['/responseStatus']);
    }

    public function testADeliveredDeliveryFinishesTheServiceWithItsProofOfDelivery(): void
    {
        $delivery = $this->receiveServiceRequest();
        $serviceId = (string) $delivery->getId();
        $activityId = sprintf('%d.transport', $delivery->getPickup()->getId());
        $this->bol->forget();

        // Picked up: the service and the activity start
        $this->taskManager()->markAsDone($delivery->getPickup());
        $this->entityManager->flush();

        $this->assertSame('STARTED', $this->bol->find('PATCH', sprintf('/services/%s', $serviceId))[0]['body']['executionStatus']);
        $this->assertSame('STARTED', $this->bol->find('PATCH', sprintf('/activities/%s', $activityId))[0]['body']['executionStatus']);
        $this->assertSame(
            ['SERVICE_STARTED', 'DEPARTURE'],
            array_map(fn (array $call) => $call['body']['code'], $this->bol->find('POST', sprintf('/services/%s/events', $serviceId)))
        );
        $this->bol->forget();

        // Delivered with a photo: the service finishes with the proof of delivery
        $dropoff = $delivery->getDropoff();
        $image = new TaskImage();
        $image->setImageName('proof-of-delivery.jpg');
        $dropoff->addImage($image);
        // Uploaded before the dropoff is marked as done, as in the app
        $this->entityManager->persist($image);
        $this->entityManager->flush();

        $this->taskManager()->markAsDone($dropoff);
        $this->entityManager->flush();

        $finished = $this->bol->find('PATCH', sprintf('/services/%s', $serviceId))[0]['body'];
        $this->assertSame('FINISHED', $finished['executionStatus']);
        $this->assertSame('Déchargement', $finished['endLocation']['action']['actionName']);
        $this->assertCount(1, $finished['documents']);
        $this->assertSame('PROOF_OF_DELIVERY', $finished['documents'][0]['documentSubtype']);
        $this->assertStringEndsWith('/proof-of-delivery.jpg', $finished['documents'][0]['cmsUri']);

        $this->assertSame('FINISHED', $this->bol->find('PATCH', sprintf('/activities/%s', $activityId))[0]['body']['executionStatus']);

        $serviceEvents = $this->bol->find('POST', sprintf('/services/%s/events', $serviceId));
        $this->assertNotEmpty($serviceEvents);
        foreach ($serviceEvents as $event) {
            $this->assertSame('PROOF_OF_DELIVERY', $event['body']['documents'][0]['documentSubtype']);
        }

        // Nothing is sent to the requestor's node: it follows our service
        foreach ($this->bol->calls() as $call) {
            $this->assertStringStartsWith(self::OUR_NODE, $call['url']);
        }
    }

    private function receiveServiceRequest(): Delivery
    {
        // Shaped like the webhooks the BOL sends in production: the service
        // request has its id but no uri, and comes twice in the same call.
        $serviceRequest = json_decode((string) file_get_contents(__DIR__.'/../../Resources/rdc/service_request.json'), true);
        $serviceRequest['id'] = basename($this->loUri);

        $event = [
            'notificationMetadata' => [
                'notificationType' => 'UPDATES',
                'resourceType' => 'ServiceRequest',
                'loUri' => $this->loUri,
                'loMemberIdentifier' => self::CONNECTION,
                'loRevision' => 1,
                'triggerType' => 'create',
                'triggerMemberIdentifier' => self::CONNECTION,
                'triggerDate' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339_EXTENDED),
            ],
            'lo' => $serviceRequest,
        ];

        $this->client->request('POST', '/api/v1/webhooks/rdc', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SECRET' => self::SECRET,
            'HTTP_X_WEBHOOK_SOURCE' => self::CONNECTION,
        ], content: (string) json_encode([$event, $event]));

        $this->assertResponseStatusCodeSame(202);
        $results = json_decode((string) $this->client->getResponse()->getContent(), true)['results'];
        $this->assertSame(['queued', 'queued'], array_column($results, 'status'));

        /** @var DeliveryRepository $repository */
        $repository = $this->entityManager->getRepository(Delivery::class);
        $delivery = $repository->findByLoUri($this->loUri);
        $this->assertNotNull($delivery, 'The service request should have become a delivery');

        return $delivery;
    }

    private function loPath(): string
    {
        return (string) parse_url($this->loUri, PHP_URL_PATH);
    }

    private function taskManager(): TaskManager
    {
        return static::getContainer()->get(TaskManager::class);
    }

    private function operationsByPath(array $changeRequest): array
    {
        return array_column($changeRequest['operations'], 'value', 'path');
    }

    private function setEnv(string $name, string $value): void
    {
        $this->previousEnv[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null];
        $_SERVER[$name] = $_ENV[$name] = $value;
    }

    private function restoreEnv(string $name, mixed $server, mixed $env): void
    {
        if (is_null($server)) {
            unset($_SERVER[$name]);
        } else {
            $_SERVER[$name] = $server;
        }

        if (is_null($env)) {
            unset($_ENV[$name]);
        } else {
            $_ENV[$name] = $env;
        }
    }
}
