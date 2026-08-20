<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\Zelty\ApiLog;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Logs the webhooks Zelty sends us, so both directions of the traffic show up
 * in the same place.
 */
class ZeltyWebhookLogSubscriber implements EventSubscriberInterface
{
    const PATH_PREFIX = '/api/zelty/webhook';

    const STARTED_AT_ATTRIBUTE = '_zelty_webhook_started_at';
    const BODY_ATTRIBUTE = '_zelty_webhook_body';

    public function __construct(private readonly ?LoggerInterface $zeltyLogger = null) {}

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

        $this->zeltyLogger?->log($statusCode >= 400 ? 'error' : 'info',
            sprintf('%s %s', $request->getMethod(), $request->getPathInfo()), [
                'direction'     => ApiLog::DIRECTION_INCOMING,
                'restaurant_id' => $this->resolveRestaurantId($request->getPathInfo()),
                'method'        => $request->getMethod(),
                'endpoint'      => $request->getPathInfo(),
                'status_code'   => $statusCode,
                'request_body'  => $request->attributes->get(self::BODY_ATTRIBUTE),
                'response_body' => $response->getContent() ?: null,
                'duration_ms'   => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
    }

    private function supports(string $path): bool
    {
        return str_contains($path, self::PATH_PREFIX);
    }

    /**
     * Only the catalog webhook is per-restaurant; the others are brand-wide, and
     * Zelty gives us nothing to map them onto a single shop.
     */
    private function resolveRestaurantId(string $path): ?int
    {
        if (preg_match('#/api/zelty/webhook/catalog/(\d+)#', $path, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }
}
