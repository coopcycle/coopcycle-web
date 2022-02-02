<?php

namespace AppBundle\MessageHandler;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\ApiApp;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\Webhook;
use AppBundle\Entity\WebhookExecution;
use AppBundle\Message\Webhook as WebhookMessage;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use SimpleBus\Message\Bus\MessageBus;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class WebhookHandler implements MessageHandlerInterface
{
    private $client;
    private $iriConverter;
    private $entityManager;

    public function __construct(
        HttpClientInterface $client,
        IriConverterInterface $iriConverter,
        EntityManagerInterface $entityManager)
    {
        $this->client = $client;
        $this->iriConverter = $iriConverter;
        $this->entityManager = $entityManager;
    }

    public function __invoke(WebhookMessage $message)
    {
        $object = $this->iriConverter->getItemFromIri($message->getObject());

        if (!$object instanceof Delivery && !$object instanceof OrderInterface) {
            return;
        }

        if ($object instanceof Delivery && null === $object->getStore()) {
            return;
        }

        if ($object instanceof OrderInterface && null === $object->getRestaurant()) {
            return;
        }

        $apps = [];
        if ($object instanceof Delivery) {
            $apps = $this->entityManager->getRepository(ApiApp::class)
                ->findBy(['store' => $object->getStore()]);
        }

        if ($object instanceof OrderInterface) {
            $apps = $this->entityManager->getRepository(ApiApp::class)
                ->findBy(['shop' => $object->getRestaurant()]);
        }

        if (count($apps) === 0) {
            return;
        }

        foreach ($apps as $app) {

            $webhooks = $this->entityManager
                ->getRepository(Webhook::class)
                ->findBy([
                    'oauth2Client' => $app->getOauth2Client(),
                    'event' => $message->getEvent()
                ]);

            foreach ($webhooks as $webhook) {

                $payload = [
                    'data' => [
                        'object' => $message->getObject(),
                        'event' => $webhook->getEvent(),
                    ]
                ];

                // https://resthooks.org/docs/security/
                // https://www.docusign.com/blog/developers/hmac-verification-php
                $hexHash = hash_hmac('sha256', json_encode($payload), $webhook->getSecret());
                $signature = base64_encode(hex2bin($hexHash));

                try {

                    $response = $this->client->request('POST', $webhook->getUrl(), [
                        'headers' => [
                            'X-CoopCycle-Signature' => $signature,
                        ],
                        'json' => $payload,
                    ]);

                    // Need to invoke a method on the Response,
                    // to actually throw the Exception here
                    // https://github.com/symfony/symfony/issues/34281
                    // https://symfony.com/doc/5.4/http_client.html#handling-exceptions
                    $content = $response->getContent();

                    $this->logExecution($webhook, $response);

                } catch (HttpExceptionInterface | TransportExceptionInterface $e) {

                    $response = $e->getResponse();

                    $this->logExecution($webhook, $response);

                    throw $e;
                }
            }
        }
    }

    private function logExecution(Webhook $webhook, ResponseInterface $response)
    {
        $execution = new WebhookExecution();
        $execution->setWebhook($webhook);
        $execution->setStatusCode($response->getStatusCode());

        $this->entityManager->persist($execution);
        $this->entityManager->flush();
    }
}
