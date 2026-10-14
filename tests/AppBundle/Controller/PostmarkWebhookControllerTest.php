<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Entity\Marketing\EmailSuppression;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PostmarkWebhookControllerTest extends WebTestCase
{
    private const SECRET = 'webhook-secret-for-tests';
    private const EMAIL = 'postmark_webhook_test@example.com';

    private ?EntityManagerInterface $entityManager = null;

    protected function tearDown(): void
    {
        if (null !== $this->entityManager) {
            $this->entityManager->createQuery('DELETE AppBundle\Entity\Marketing\EmailSuppression s WHERE s.email = :email')
                ->setParameter('email', self::EMAIL)
                ->execute();
        }

        parent::tearDown();
    }

    private function client(): KernelBrowser
    {
        $client = self::createClient();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $settingsManager = self::getContainer()->get(SettingsManager::class);
        $settingsManager->set('postmark_webhook_secret', self::SECRET);
        $settingsManager->flush();

        return $client;
    }

    private function post(KernelBrowser $client, array $payload, ?string $secret = self::SECRET): void
    {
        $client->request(
            'POST',
            '/webhooks/postmark' . (null === $secret ? '' : '?secret=' . urlencode($secret)),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
    }

    private function suppressions(): array
    {
        return $this->entityManager->getRepository(EmailSuppression::class)
            ->findBy(['email' => self::EMAIL]);
    }

    public function testRecordsAHardBounce(): void
    {
        $client = $this->client();

        $this->post($client, [
            'RecordType' => 'Bounce',
            'Type' => 'HardBounce',
            'Email' => self::EMAIL,
            'Inactive' => true,
            'MessageStream' => 'broadcast',
            'BouncedAt' => '2026-10-09T10:00:00Z',
        ]);

        self::assertResponseIsSuccessful();

        $suppressions = $this->suppressions();
        self::assertCount(1, $suppressions);
        self::assertSame(EmailSuppression::REASON_HARD_BOUNCE, $suppressions[0]->getReason());
        self::assertSame('broadcast', $suppressions[0]->getMessageStream());
    }

    /**
     * Postmark retries anything it doesn't get a 2xx for, so the same event
     * arriving twice has to be harmless.
     */
    public function testTheSameEventTwiceRecordsOneSuppression(): void
    {
        $client = $this->client();

        $payload = [
            'RecordType' => 'SpamComplaint',
            'Email' => self::EMAIL,
            'MessageStream' => 'broadcast',
        ];

        $this->post($client, $payload);
        $this->post($client, $payload);

        self::assertCount(1, $this->suppressions());
    }

    public function testResubscribingRemovesTheSuppression(): void
    {
        $client = $this->client();

        $this->post($client, [
            'RecordType' => 'SubscriptionChange',
            'MessageStream' => 'broadcast',
            'Recipient' => self::EMAIL,
            'SuppressSending' => true,
            'SuppressionReason' => 'ManualSuppression',
            'Origin' => 'Recipient',
        ]);

        self::assertCount(1, $this->suppressions());

        $this->post($client, [
            'RecordType' => 'SubscriptionChange',
            'MessageStream' => 'broadcast',
            'Recipient' => self::EMAIL,
            'SuppressSending' => false,
        ]);

        self::assertCount(0, $this->suppressions());
    }

    /**
     * Suppressions are per stream: unsubscribing from marketing must not
     * stop a customer's order receipts.
     */
    public function testSuppressionsAreRecordedPerStream(): void
    {
        $client = $this->client();

        foreach (['broadcast', 'outbound'] as $stream) {
            $this->post($client, [
                'RecordType' => 'SpamComplaint',
                'Email' => self::EMAIL,
                'MessageStream' => $stream,
            ]);
        }

        self::assertCount(2, $this->suppressions());
    }

    public function testRejectsAWrongSecret(): void
    {
        $client = $this->client();

        $this->post($client, ['RecordType' => 'SpamComplaint', 'Email' => self::EMAIL], 'not-the-secret');

        self::assertResponseStatusCodeSame(401);
        self::assertCount(0, $this->suppressions());
    }

    public function testRejectsAMissingSecret(): void
    {
        $client = $this->client();

        $this->post($client, ['RecordType' => 'SpamComplaint', 'Email' => self::EMAIL], null);

        self::assertResponseStatusCodeSame(401);
        self::assertCount(0, $this->suppressions());
    }

    public function testRejectsAnInvalidPayload(): void
    {
        $client = $this->client();

        $client->request(
            'POST',
            '/webhooks/postmark?secret=' . self::SECRET,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'not json'
        );

        self::assertResponseStatusCodeSame(400);
    }
}
