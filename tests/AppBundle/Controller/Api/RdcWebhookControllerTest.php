<?php

declare(strict_types=1);

namespace Tests\AppBundle\Controller\Api;

use AppBundle\Controller\Api\RdcWebhookController;
use AppBundle\Message\RdcMessage;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class RdcWebhookControllerTest extends TestCase
{
    private const SOURCE = 'BOL.MEMBER.REQUESTOR';
    private const OTHER_SOURCE = 'BOL.MEMBER.OTHER_REQUESTOR';
    private const SECRET = 'requestor-secret';

    private MessageBusInterface&MockObject $messageBus;
    private TestHandler $logs;
    /** @var RdcMessage[] */
    private array $dispatched = [];

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->messageBus->method('dispatch')->willReturnCallback(function (RdcMessage $message) {
            $this->dispatched[] = $message;

            return new Envelope($message);
        });

        $this->logs = new TestHandler();
    }

    public function testQueuesAServiceRequestForTheConnectionThatSentIt(): void
    {
        $response = $this->post([$this->event('ServiceRequest', 'create')]);

        $this->assertSame(202, $response['status']);
        $this->assertSame('queued', $response['results'][0]['status']);
        $this->assertCount(1, $this->dispatched);
        $this->assertSame(self::SOURCE, $this->dispatched[0]->loMember);
    }

    public function testRefusesAnUnknownSource(): void
    {
        $response = $this->post([$this->event('ServiceRequest', 'create')], source: 'BOL.MEMBER.UNKNOWN');

        $this->assertSame(401, $response['status']);
        $this->assertSame([], $this->dispatched);
        $this->assertTrue($this->logs->hasWarningThatContains('unknown source'));
    }

    public function testRefusesAConnectionWithoutSecretEvenWithAnEmptyHeader(): void
    {
        $response = $this->post([$this->event('ServiceRequest', 'create')], secret: '', source: self::OTHER_SOURCE);

        $this->assertSame(401, $response['status']);
        $this->assertSame([], $this->dispatched);
        $this->assertTrue($this->logs->hasErrorThatContains('no webhook secret'));
    }

    public function testRefusesTheSecretOfAnotherConnection(): void
    {
        // A requestor can't post as another one: each connection has its own secret
        $connections = [
            self::SOURCE => ['webhookSecret' => self::SECRET],
            self::OTHER_SOURCE => ['webhookSecret' => 'other-secret'],
        ];

        $response = $this->post([$this->event('ServiceRequest', 'create')], source: self::OTHER_SOURCE, connections: $connections);

        $this->assertSame(401, $response['status']);
        $this->assertSame([], $this->dispatched);
        $this->assertTrue($this->logs->hasWarningThatContains('invalid secret'));
    }

    public function testRejectsTheOtherResourceTypes(): void
    {
        // A service or an activity would otherwise be read as a new service request
        $response = $this->post([$this->event('Service', 'create'), $this->event('Activity', 'update')]);

        $this->assertSame(202, $response['status']);
        $this->assertSame(['error', 'error'], array_column($response['results'], 'status'));
        $this->assertSame([], $this->dispatched);
        $this->assertTrue($this->logs->hasWarningThatContains('Unsupported resource type'));
    }

    public function testSkipsTheOtherTriggers(): void
    {
        $response = $this->post([$this->event('ServiceRequest', 'delete')]);

        $this->assertSame('skipped', $response['results'][0]['status']);
        $this->assertSame([], $this->dispatched);
        $this->assertTrue($this->logs->hasInfoThatContains('skipped'));
    }

    public function testRejectsAMalformedEventAndQueuesTheOthers(): void
    {
        $response = $this->post([
            'not an event',
            ['notificationMetadata' => 'not metadata', 'lo' => []],
            ['notificationMetadata' => ['loUri' => ['not a uri'], 'notificationType' => 'UPDATES'], 'lo' => ['id' => 'sr-1']],
            $this->event('ServiceRequest', 'create'),
        ]);

        $this->assertSame(202, $response['status']);
        $this->assertSame(['error', 'error', 'error', 'queued'], array_column($response['results'], 'status'));
        $this->assertCount(1, $this->dispatched);
        $this->assertTrue($this->logs->hasWarningThatContains('Missing required metadata'));
    }

    public function testRefusesAPayloadThatIsNotAListOfEvents(): void
    {
        $response = $this->post($this->event('ServiceRequest', 'create'));

        $this->assertSame(400, $response['status']);
        $this->assertSame([], $this->dispatched);
    }

    private function post(array $payload, string $secret = self::SECRET, string $source = self::SOURCE, ?array $connections = null): array
    {
        $controller = new RdcWebhookController(
            new Logger('rdc', [$this->logs]),
            $this->messageBus,
            $connections ?? [
                self::SOURCE => ['webhookSecret' => self::SECRET],
                // Configured without secret
                self::OTHER_SOURCE => [],
            ],
            true,
        );

        $request = Request::create('/api/v1/webhooks/rdc', 'POST', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SECRET' => $secret,
            'HTTP_X_WEBHOOK_SOURCE' => $source,
        ], content: (string) json_encode($payload));

        $response = $controller->handleWebhook($request);
        $body = json_decode((string) $response->getContent(), true);

        return ['status' => $response->getStatusCode(), 'results' => $body['results'] ?? null];
    }

    private function event(string $resourceType, string $triggerType): array
    {
        return [
            'notificationMetadata' => [
                'notificationType' => 'UPDATES',
                'resourceType' => $resourceType,
                'loUri' => 'https://bol-api.requestor.test/service-requests/sr-1',
                'loRevision' => 1,
                'triggerType' => $triggerType,
                'triggerDate' => '2026-11-05T10:00:00.000Z',
            ],
            'lo' => ['id' => 'sr-1'],
        ];
    }
}
