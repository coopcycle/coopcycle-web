<?php

declare(strict_types=1);

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Integration\Rdc\Coopcycle\RdcServiceRequestProcessor;
use AppBundle\Integration\Rdc\RdcStoreResolver;
use AppBundle\Message\RdcMessage;
use AppBundle\MessageHandler\RdcMessageHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Redis;

class RdcMessageHandlerTest extends TestCase
{
    private RdcServiceRequestProcessor&MockObject $processor;
    private Redis&MockObject $redis;
    private RdcMessageHandler $handler;

    protected function setUp(): void
    {
        $this->processor = $this->createMock(RdcServiceRequestProcessor::class);
        $this->redis = $this->createMock(Redis::class);

        $storeResolver = $this->createMock(RdcStoreResolver::class);
        $storeResolver->method('resolveStore')->willReturn(new Store());

        $this->handler = new RdcMessageHandler(
            $this->processor,
            $storeResolver,
            $this->redis,
            new NullLogger(),
            true,
        );
    }

    public function testReleasesClaimWhenProcessingFails(): void
    {
        $this->redis->method('set')->willReturn(true);
        $this->processor->method('process')->willThrowException(new \RuntimeException('BOL is down'));

        // Otherwise Messenger's retry would be skipped as already processed
        $this->redis->expects($this->once())
            ->method('del')
            ->with($this->stringStartsWith('rdc_webhook_event:'));

        $this->expectException(\RuntimeException::class);

        ($this->handler)($this->buildMessage());
    }

    public function testKeepsClaimWhenProcessingSucceeds(): void
    {
        $this->redis->method('set')->willReturn(true);
        $this->processor->method('process')->willReturn(new Delivery());

        $this->redis->expects($this->never())->method('del');

        ($this->handler)($this->buildMessage());
    }

    public function testSkipsAlreadyClaimedEvent(): void
    {
        $this->redis->method('set')->willReturn(false);

        $this->processor->expects($this->never())->method('process');

        ($this->handler)($this->buildMessage());
    }

    private function buildMessage(): RdcMessage
    {
        $loUri = 'https://bol-api.example.com/service-requests/f9b10095';

        return new RdcMessage(
            loPayload: [],
            loMember: 'BOL.MEMBER.APPLICOLIS',
            loUri: $loUri,
            loRevision: 1,
            notificationMetadata: [
                'loUri' => $loUri,
                'loRevision' => 1,
                'notificationType' => 'UPDATES',
                'triggerType' => 'create',
                'triggerDate' => '2026-10-21T14:18:57.355Z',
            ],
        );
    }
}
