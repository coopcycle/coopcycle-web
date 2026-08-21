<?php

namespace Tests\AppBundle\Monolog;

use AppBundle\Monolog\ZeltyApiLogHandler;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

class ZeltyApiLogHandlerTest extends TestCase
{
    private $handler;

    protected function setUp(): void
    {
        $connection = $this->createMock(Connection::class);

        $this->handler = new class($connection) extends ZeltyApiLogHandler {
            public function prepare(mixed $body): ?string
            {
                return $this->prepareBody($body);
            }
        };
    }

    public function testEmptyBodiesAreStoredAsNull(): void
    {
        $this->assertNull($this->handler->prepare(null));
        $this->assertNull($this->handler->prepare(''));
    }

    public function testArrayBodiesAreEncoded(): void
    {
        $this->assertSame('{"name":"Margherita"}', $this->handler->prepare(['name' => 'Margherita']));
    }

    public function testWebhookSecretIsRedacted(): void
    {
        $this->assertSame(
            '{"webhooks":{},"secret_key":"[redacted]","errno":0}',
            $this->handler->prepare('{"webhooks":{},"secret_key":"9f8a7b6c5d","errno":0}')
        );
    }

    public function testLongBodiesAreTruncated(): void
    {
        $body = str_repeat('a', ZeltyApiLogHandler::MAX_BODY_LENGTH + 500);

        $prepared = $this->handler->prepare($body);

        $this->assertStringEndsWith('… [truncated]', $prepared);
        $this->assertSame(
            ZeltyApiLogHandler::MAX_BODY_LENGTH + mb_strlen('… [truncated]'),
            mb_strlen($prepared)
        );
    }
}
