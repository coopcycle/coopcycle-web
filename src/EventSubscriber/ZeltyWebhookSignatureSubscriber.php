<?php

namespace AppBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Verifies the HMAC Zelty signs its webhooks with.
 *
 * The ^/api/zelty/webhook firewall is security: false — Zelty has no account
 * here, the signature is the only thing that says a webhook is really theirs.
 * That check lives here rather than in the processors so that it covers the
 * whole path: a handler added later is signed-only without anyone remembering
 * to wire it up.
 *
 * Fails closed. An instance with no ZELTY_WEBHOOK_SECRET cannot tell a real
 * webhook from a forged one, so it rejects both.
 */
class ZeltyWebhookSignatureSubscriber implements EventSubscriberInterface
{
    const PATH_PREFIX = '/api/zelty/webhook';

    const SIGNATURE_HEADER = 'x-zelty-hmac-sha256';

    public function __construct(
        private readonly string $zeltyWebhookSecret = '',
    ) {}

    public static function getSubscribedEvents(): array
    {
        // Below ZeltyWebhookLogSubscriber, so that a rejected webhook has still
        // been picked up for logging by the time it is turned away.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 2048],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), self::PATH_PREFIX)) {
            return;
        }

        if ($this->zeltyWebhookSecret === '') {
            throw new AccessDeniedHttpException('Zelty webhook secret is not configured');
        }

        $signature = $request->headers->get(self::SIGNATURE_HEADER);
        if ($signature === null) {
            throw new AccessDeniedHttpException('Missing webhook signature');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $this->zeltyWebhookSecret);
        if (!hash_equals($expected, $signature)) {
            throw new AccessDeniedHttpException('Invalid webhook signature');
        }
    }
}
