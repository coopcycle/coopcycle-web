<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\CreateWebhookEndpoint;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\StripeManager;
use Craue\ConfigBundle\Entity\BaseSetting;
use Doctrine\ORM\EntityManagerInterface;
use Stripe;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * @see https://stripe.com/docs/api/webhook_endpoints/create?lang=php
 */
class CreateWebhookEndpointHandler implements MessageHandlerInterface
{
    private SettingsManager $settingsManager;
    private EntityManagerInterface $entityManager;
    private $entityName;

    public function __construct(
        SettingsManager $settingsManager, EntityManagerInterface $entityManager, string $entityName)
    {
        $this->settingsManager = $settingsManager;
        $this->entityManager = $entityManager;
        $this->entityName = $entityName;
    }

    public function __invoke(CreateWebhookEndpoint $message)
    {
        $mode = $message->getMode();

        $secretKey = $this->settingsManager->get(sprintf('stripe_%s_secret_key', $mode));

        if (null === $secretKey) {
            return;
        }

        $stripe = new Stripe\StripeClient([
            'api_key' => $secretKey,
            'stripe_version' => StripeManager::STRIPE_API_VERSION,
        ]);

        // We use the repository to avoid cache
        $repository = $this->entityManager->getRepository($this->entityName);
        $webhookIdSetting = $repository->findOneByName('stripe_webhook_id');
        $webhookSecretSetting = $repository->findOneByName('stripe_webhook_secret');

        // MIGRATE LEGACY DATA
        // Rename settings "stripe_webhook_*" to "stripe_<test|live>_webhook_*"
        if ($webhookIdSetting && $webhookSecretSetting) {
            $this->migrate($stripe, $mode, $webhookIdSetting, $webhookSecretSetting);
            return;
        }

        $webhookId = $this->settingsManager->get(sprintf('stripe_%s_webhook_id', $mode));

        if (null !== $webhookId) {

            $webhookEndpoint = $stripe->webhookEndpoints->retrieve($webhookId);

            $stripe->webhookEndpoints->update($webhookEndpoint->id, [
                'url' => $message->getUrl(),
                'enabled_events' => $message->getEvents(),
            ]);

        } else {

            $webhookEndpoint = $stripe->webhookEndpoints->create([
                'url' => $message->getUrl(),
                'enabled_events' => $message->getEvents(),
                'connect' => true,
            ]);

            $webhookId = $webhookEndpoint->id;

            $this->settingsManager->set(sprintf('stripe_%s_webhook_id', $mode), $webhookEndpoint->id);
            $this->settingsManager->set(sprintf('stripe_%s_webhook_secret', $mode), $webhookEndpoint->secret);
            $this->settingsManager->flush();
        }

        // Make sure there are no duplicates

        $webhooksWithSameUrl = [];
        foreach ($stripe->webhookEndpoints->all() as $webhook) {
            if ($webhook->url === $message->getUrl()) {
                $webhooksWithSameUrl[] = $webhook;
            }
        }

        if (count($webhooksWithSameUrl) > 1) {
            foreach ($webhooksWithSameUrl as $webhook) {
                if ($webhook->id !== $webhookId) {
                    $stripe->webhookEndpoints->delete($webhook->id, []);
                }
            }
        }
    }

    private function migrate(
        Stripe\StripeClient $stripe,
        string $mode,
        BaseSetting $webhookIdSetting,
        BaseSetting $webhookSecretSetting)
    {
        try {

            $webhookEndpoint = $stripe->webhookEndpoints->retrieve($webhookIdSetting->getValue());

            // If there is no exception,
            // this means the webhook was created in the same mode
            $key = $mode;

        } catch (Stripe\Exception\InvalidRequestException $e) {
            // This means the webhook was created in the *opposite* mode
            $key = $mode === 'live' ? 'test' : 'live';
        }

        // We just rename the settings
        $webhookIdSetting->setName(sprintf('stripe_%s_webhook_id', $key));
        $webhookSecretSetting->setName(sprintf('stripe_%s_webhook_secret', $key));

        $this->settingsManager->flush();
    }
}
