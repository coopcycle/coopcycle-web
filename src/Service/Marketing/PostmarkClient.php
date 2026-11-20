<?php

namespace AppBundle\Service\Marketing;

use AppBundle\Service\SettingsManager;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The bit of Postmark that Symfony's mailer bridge doesn't cover: sending
 * goes through the mailer, but the suppression list is a plain REST API.
 *
 * @see https://postmarkapp.com/developer/api/suppressions-api
 */
class PostmarkClient
{
    private const BASE_URL = 'https://api.postmarkapp.com';

    public const DEFAULT_BROADCAST_STREAM = 'broadcast';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    public function isConfigured(): bool
    {
        return !empty($this->settingsManager->get('postmark_server_token'));
    }

    public function getBroadcastStream(): string
    {
        return $this->settingsManager->get('postmark_broadcast_stream')
            ?: self::DEFAULT_BROADCAST_STREAM;
    }

    /**
     * The whole suppression list for a stream, as Postmark holds it.
     *
     * Used to seed the platform's copy: webhooks only tell us about events
     * from the moment they're wired up, so without this an instance that has
     * been sending for a while starts out believing nobody ever unsubscribed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSuppressions(?string $messageStream = null): array
    {
        $response = $this->httpClient->request(
            'GET',
            sprintf('%s/message-streams/%s/suppressions/dump', self::BASE_URL, $messageStream ?? $this->getBroadcastStream()),
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Postmark-Server-Token' => (string) $this->settingsManager->get('postmark_server_token'),
                ],
            ]
        );

        return $response->toArray()['Suppressions'] ?? [];
    }
}
