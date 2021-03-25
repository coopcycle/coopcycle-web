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
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SimpleBus\Message\Bus\MessageBus;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class WebhookHandler implements MessageHandlerInterface
{
    private $client;
    private $iriConverter;
    private $entityManager;
    private $logger;

    public function __construct(
        Client $client,
        IriConverterInterface $iriConverter,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->iriConverter = $iriConverter;
        $this->entityManager = $entityManager;
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(WebhookMessage $message)
    {
        $object = $this->iriConverter->getItemFromIri($message->getObject());

        if (!$object instanceof Delivery) {
            return;
        }

        if (null === $object->getStore()) {
            return;
        }

        $apps = $this->entityManager->getRepository(ApiApp::class)
            ->findBy(['store' => $object->getStore()]);

        if (count($apps) === 0) {
            return;
        }

        foreach ($apps as $app) {

            $webhook = $this->entityManager
                ->getRepository(Webhook::class)
                ->findOneBy([
                    'oauth2Client' => $app->getOauth2Client(),
                    'event' => $message->getEvent()
                ]);

            if ($webhook) {

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

                    $response = $this->client->post($webhook->getUrl(), [
                        'headers' => [
                            'X-CoopCycle-Signature' => $signature,
                        ],
                        'json' => $payload,
                    ]);

                    $this->logExecution($webhook, $response);

                } catch (RequestException $e) {

                    $response = $e->getResponse();

                    $this->logExecution($webhook, $response);

                    throw $e;
                }
            }
        }
    }

    private function logExecution(Webhook $webhook, Response $response)
    {
        $execution = new WebhookExecution();
        $execution->setWebhook($webhook);
        $execution->setStatusCode($response->getStatusCode());

        $this->entityManager->persist($execution);
        $this->entityManager->flush();
    }
}
