<?php

namespace Tests\AppBundle\EventSubscriber;

use AppBundle\EventSubscriber\ZeltyWebhookLogSubscriber;
use AppBundle\Integration\Zelty\ZeltyActivityRecorder;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ZeltyWebhookLogSubscriberTest extends TestCase
{
    use ProphecyTrait;

    private $logger;
    private ZeltyActivityRecorder $recorder;
    private ZeltyWebhookLogSubscriber $subscriber;

    public function setUp(): void
    {
        $this->logger = $this->prophesize(LoggerInterface::class);
        $this->recorder = new ZeltyActivityRecorder();
        $this->subscriber = new ZeltyWebhookLogSubscriber($this->recorder, $this->logger->reveal());
    }

    private function handle(string $path, int $statusCode, callable $duringRequest = null): Request
    {
        $kernel = $this->prophesize(HttpKernelInterface::class)->reveal();
        $request = Request::create($path, 'POST', [], [], [], [], '{"event":"dish.update"}');

        $this->subscriber->onKernelRequest(
            new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST)
        );

        if ($duringRequest !== null) {
            $duringRequest($this->recorder);
        }

        $this->subscriber->onKernelResponse(new ResponseEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response('{"status":"success"}', $statusCode)
        ));

        return $request;
    }

    public function testWebhookThatChangedNothingIsNotLogged()
    {
        $this->logger->log(Argument::cetera())->shouldNotBeCalled();

        $this->handle('/api/zelty/webhook/dish.update', 200);
    }

    public function testWebhookThatChangedSomethingIsLogged()
    {
        $this->logger
            ->log('info', Argument::any(), Argument::that(function (array $context) {
                return $context['restaurant_id'] === 42
                    && count($context['events']) === 1
                    && $context['events'][0]['type'] === ZeltyActivityRecorder::PRODUCT_DISABLED;
            }))
            ->shouldBeCalledTimes(1);

        $this->handle('/api/zelty/webhook/dish.update', 200, function (ZeltyActivityRecorder $recorder) {
            $recorder->setRestaurantId(42);
            $recorder->record(ZeltyActivityRecorder::PRODUCT_DISABLED, ['id' => 1, 'name' => 'Pizza']);
        });
    }

    public function testFailedWebhookIsLoggedEvenWithoutActivity()
    {
        $this->logger->log('error', Argument::cetera())->shouldBeCalledTimes(1);

        $this->handle('/api/zelty/webhook/dish.update', 500);
    }

    public function testEventsDoNotLeakIntoTheNextWebhook()
    {
        $this->logger->log(Argument::cetera())->shouldBeCalledTimes(1);

        $this->handle('/api/zelty/webhook/dish.update', 200, function (ZeltyActivityRecorder $recorder) {
            $recorder->record(ZeltyActivityRecorder::PRODUCT_DISABLED, ['id' => 1, 'name' => 'Pizza']);
        });

        // The second webhook changed nothing, so nothing more must be logged
        $this->handle('/api/zelty/webhook/dish.update', 200);
    }

    public function testNonZeltyRequestIsIgnored()
    {
        $this->logger->log(Argument::cetera())->shouldNotBeCalled();

        $this->handle('/api/orders', 200, function (ZeltyActivityRecorder $recorder) {
            $recorder->record(ZeltyActivityRecorder::PRODUCT_DISABLED, ['id' => 1, 'name' => 'Pizza']);
        });
    }
}
