<?php

namespace Tests\AppBundle\Integration\Rdc;

use AppBundle\Integration\Rdc\Webhook\WebhookPayloadParser;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class WebhookPayloadParserTest extends TestCase
{
    private WebhookPayloadParser $parser;

    protected function setUp(): void
    {
        $this->parser = new WebhookPayloadParser(new NullLogger());
    }

    public function testParseValidPayload(): void
    {
        $payload = [
            [
                'metadata' => [
                    'loUri' => 'lo://rdc/service-request/123',
                    'eventType' => 'create',
                ],
                'lo' => [
                    'serviceRequestId' => 'SR-123',
                    'serviceType' => 'delivery',
                ],
            ]
        ];

        $result = $this->parser->parse($payload);

        $this->assertNotNull($result);
        $this->assertEquals('lo://rdc/service-request/123', $result['loUri']);
        $this->assertEquals('create', $result['eventType']);
        $this->assertEquals('SR-123', $result['lo']['serviceRequestId']);
    }

    public function testParseReturnsNullForEmptyPayload(): void
    {
        $this->assertNull($this->parser->parse([]));
        $this->assertNull($this->parser->parse(['not-empty' => 'value']));
    }

    public function testParseReturnsNullForMissingMetadata(): void
    {
        $payload = [
            [
                'metadata' => [],
                'lo' => [],
            ]
        ];

        $this->assertNull($this->parser->parse($payload));
    }

    public function testParseReturnsNullForMissingLoField(): void
    {
        $payload = [
            [
                'metadata' => [
                    'loUri' => 'lo://rdc/service-request/123',
                    'eventType' => 'create',
                ],
            ]
        ];

        $this->assertNull($this->parser->parse($payload));
    }
}