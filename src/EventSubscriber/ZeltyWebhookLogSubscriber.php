<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\Zelty\ApiLog;
use AppBundle\Integration\Zelty\ZeltyActivityRecorder;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Logs the webhooks Zelty sends us, so both directions of the traffic show up
 * in the same place.
 *
 * Zelty broadcasts its webhooks brand-wide, so most of them are about dishes and
 * orders that have nothing to do with us. Only the ones that actually changed
 * something here — as reported by ZeltyActivityRecorder — are kept, along with
 * every failure.
 */
class ZeltyWebhookLogSubscriber implements EventSubscriberInterface
{
    const PATH_PREFIX = '/api/zelty/webhook';

    const STARTED_AT_ATTRIBUTE = '_zelty_webhook_started_at';
    const BODY_ATTRIBUTE = '_zelty_webhook_body';

    public function __construct(
        private readonly ZeltyActivityRecorder $activityRecorder,
        private readonly ?LoggerInterface $zeltyLogger = null,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 4096],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->supports($request->getPathInfo())) {
            return;
        }

        // A worker or a sub-request may have left events behind
        $this->activityRecorder->reset();

        // The body has to be read now: a processor may have consumed it by the time
        // the response is sent.
        $request->attributes->set(self::STARTED_AT_ATTRIBUTE, microtime(true));
        $request->attributes->set(self::BODY_ATTRIBUTE, $request->getContent());
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->attributes->has(self::BODY_ATTRIBUTE)) {
            return;
        }

        $response = $event->getResponse();
        $statusCode = $response->getStatusCode();
        $startedAt = $request->attributes->get(self::STARTED_AT_ATTRIBUTE);

        $events = $this->activityRecorder->getEvents();
        $restaurantId = $this->activityRecorder->getRestaurantId()
            ?? $this->resolveRestaurantId($request->getPathInfo());
        $this->activityRecorder->reset();

        // A webhook about someone else's dishes changed nothing here: it is noise.
        if (count($events) === 0 && $statusCode < 400) {
            return;
        }

        $this->zeltyLogger?->log($statusCode >= 400 ? 'error' : 'info',
            sprintf('%s %s', $request->getMethod(), $request->getPathInfo()), [
                'direction'     => ApiLog::DIRECTION_INCOMING,
                'restaurant_id' => $restaurantId,
                'method'        => $request->getMethod(),
                'endpoint'      => $request->getPathInfo(),
                'status_code'   => $statusCode,
                'request_body'  => $request->attributes->get(self::BODY_ATTRIBUTE),
                'response_body' => $response->getContent() ?: null,
                'duration_ms'   => (int) round((microtime(true) - $startedAt) * 1000),
                'events'        => $events,
            ]);
    }

    private function supports(string $path): bool
    {
        return str_contains($path, self::PATH_PREFIX);
    }

    /**
     * Fallback when nothing was recorded: only the catalog webhook carries the shop
     * in its URL. The others are brand-wide, and are attributed by the recorder
     * from whatever entity they touched.
     */
    private function resolveRestaurantId(string $path): ?int
    {
        if (preg_match('#/api/zelty/webhook/catalog/(\d+)#', $path, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }
}
