<?php

namespace AppBundle\Controller;

use AppBundle\Service\Marketing\MarketingAutomationStatus;
use AppBundle\Service\Marketing\PostmarkSuppressionEventHandler;
use AppBundle\Service\SettingsManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Receives bounce, spam-complaint and subscription-change events so the
 * platform knows who must not be emailed.
 *
 * Unlike Stripe, Postmark does not sign its webhooks -- the documented way to
 * secure the endpoint is to put credentials in the URL you give Postmark. We
 * accept the shared secret either as HTTP basic auth (what Postmark's own UI
 * encourages) or as a query parameter, because basic auth is the kind of
 * thing a reverse proxy strips without telling anyone.
 */
class PostmarkWebhookController extends AbstractController
{
    public function __construct(
        private readonly MarketingAutomationStatus $marketingAutomationStatus,
        private readonly SettingsManager $settingsManager,
        private readonly PostmarkSuppressionEventHandler $eventHandler,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route(path: '/webhooks/postmark', name: 'webhooks_postmark', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        // Gated on the env var alone, not the runtime switch: an admin
        // pausing campaigns must not stop unsubscribes being recorded.
        if (!$this->marketingAutomationStatus->isEnvEnabled()) {
            return new JsonResponse(['error' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isAuthorized($request)) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $handled = $this->eventHandler->handle($payload);
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Failed to handle Postmark webhook: %s', $e->getMessage()));

            // Postmark retries non-2xx, which is what we want for a failure
            // on our side -- losing a suppression means emailing someone who
            // asked us not to.
            return new JsonResponse(['error' => 'Internal error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['handled' => $handled]);
    }

    private function isAuthorized(Request $request): bool
    {
        $secret = $this->settingsManager->get('postmark_webhook_secret');

        if (empty($secret)) {
            $this->logger->warning('Postmark webhook called, but no webhook secret is configured');

            return false;
        }

        $provided = $request->headers->get('PHP_AUTH_PW')
            ?? $request->getPassword()
            ?? $request->query->get('secret');

        return is_string($provided) && hash_equals($secret, $provided);
    }
}
