<?php

namespace Tests\AppBundle\Service\Marketing;

use AppBundle\Entity\Marketing\Campaign;
use AppBundle\Entity\Marketing\CampaignRecipient;
use AppBundle\Entity\Marketing\CampaignRecipientRepository;
use AppBundle\Message\Marketing\SendCampaignEmail;
use AppBundle\MessageHandler\Marketing\SendCampaignEmailHandler;
use AppBundle\Service\Marketing\CampaignAudienceResolver;
use AppBundle\Service\Marketing\CampaignEmailFactory;
use AppBundle\Service\Marketing\MarketingMailer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;

/**
 * The send path, against the real schema.
 *
 * Everything worth getting wrong here is about repetition: a retried
 * message sending twice, a campaign never closing, two campaigns both
 * reaching the same person. None of that shows up in a single happy-path
 * send, so each is provoked deliberately.
 */
class CampaignSendingIntegrationTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?Campaign $campaign = null;
    private MarketingMailer&MockObject $marketingMailer;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->marketingMailer = $this->createMock(MarketingMailer::class);
        $this->marketingMailer->method('send')->willReturn(true);

        $this->campaign = new Campaign();
        $this->campaign->setName('Test campaign');
        $this->campaign->setSegment('champions');
        $this->campaign->setSubject('Hello');
        $this->campaign->setBodyHtml('<p>Hello</p>');
        $this->campaign->setSenderEmail('hello@example.com');
        $this->campaign->setSenderName('CoopCycle');
        $this->campaign->setStatus(Campaign::STATUS_SENDING);
        $this->entityManager->persist($this->campaign);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();

        if (null !== $this->campaign && null !== $this->campaign->getId()) {
            foreach (['AppBundle\Entity\Marketing\CampaignRecipient r WHERE r.campaign' => $this->campaign->getId(),
                      'AppBundle\Entity\Marketing\Campaign c WHERE c.id' => $this->campaign->getId()] as $dql => $id) {
                $this->entityManager->createQuery(sprintf('DELETE %s = :id', $dql))
                    ->setParameter('id', $id)
                    ->execute();
            }
        }

        parent::tearDown();
    }

    private function handler(): SendCampaignEmailHandler
    {
        return new SendCampaignEmailHandler(
            self::getContainer()->get(CampaignRecipientRepository::class),
            self::getContainer()->get(CampaignAudienceResolver::class),
            // The real factory, so these tests keep covering the message
            // that actually goes out rather than one built for the test.
            new CampaignEmailFactory($this->marketingMailer),
            $this->marketingMailer,
            $this->entityManager,
            new NullLogger()
        );
    }

    private function recipient(string $email): CampaignRecipient
    {
        $recipient = CampaignRecipient::create($this->campaign, $email);
        $this->entityManager->persist($recipient);
        $this->entityManager->flush();

        return $recipient;
    }

    public function testSendingMarksTheRecipientSentAndClosesTheCampaign(): void
    {
        $recipient = $this->recipient('send_test_one@example.com');

        $this->handler()(new SendCampaignEmail($recipient));

        self::assertSame(CampaignRecipient::STATUS_SENT, $recipient->getStatus());
        self::assertNotNull($recipient->getSentAt());
        self::assertSame(Campaign::STATUS_SENT, $this->campaign->getStatus());
        self::assertNotNull($this->campaign->getSentAt());
    }

    /**
     * Messenger retries, and a retry that sends again would mean a customer
     * getting the same campaign twice.
     */
    public function testHandlingTheSameMessageTwiceSendsOnce(): void
    {
        $recipient = $this->recipient('send_test_retry@example.com');

        $this->marketingMailer = $this->createMock(MarketingMailer::class);
        $this->marketingMailer->expects(self::once())->method('send')->willReturn(true);

        $message = new SendCampaignEmail($recipient);
        $handler = $this->handler();

        $handler($message);
        $handler($message);

        self::assertSame(CampaignRecipient::STATUS_SENT, $recipient->getStatus());
    }

    /**
     * A campaign with recipients still to go must not be marked finished,
     * or its stats would claim it was done while mail was still going out.
     */
    public function testCampaignStaysOpenUntilEveryRecipientIsDone(): void
    {
        $first = $this->recipient('send_test_first@example.com');
        $this->recipient('send_test_second@example.com');

        $this->handler()(new SendCampaignEmail($first));

        self::assertSame(Campaign::STATUS_SENDING, $this->campaign->getStatus());
        self::assertNull($this->campaign->getSentAt());
    }

    public function testASuppressedRecipientIsRecordedRatherThanSent(): void
    {
        $recipient = $this->recipient('send_test_suppressed@example.com');

        $this->marketingMailer = $this->createMock(MarketingMailer::class);
        // What MarketingMailer returns when every recipient is suppressed.
        $this->marketingMailer->method('send')->willReturn(false);

        $this->handler()(new SendCampaignEmail($recipient));

        self::assertSame(CampaignRecipient::STATUS_SUPPRESSED, $recipient->getStatus());
        self::assertNull($recipient->getSentAt());
    }

    /**
     * One bad address must not take the campaign down with it, and must not
     * be retried forever by Messenger.
     */
    public function testAFailedSendIsRecordedAndDoesNotThrow(): void
    {
        $recipient = $this->recipient('send_test_failure@example.com');

        $this->marketingMailer = $this->createMock(MarketingMailer::class);
        $this->marketingMailer->method('send')
            ->willThrowException(new TransportException('Postmark said no'));

        $this->handler()(new SendCampaignEmail($recipient));

        self::assertSame(CampaignRecipient::STATUS_FAILED, $recipient->getStatus());
        self::assertStringContainsString('Postmark said no', $recipient->getError());
        // Still counts as finished, so the campaign isn't left hanging.
        self::assertSame(Campaign::STATUS_SENT, $this->campaign->getStatus());
    }

    /**
     * Two campaigns going out at once both resolve their audience before
     * either has sent anything, so the cap has to be applied again here.
     */
    public function testTheFrequencyCapIsAppliedAgainAtSendTime(): void
    {
        $email = 'send_test_capped@example.com';

        // Another campaign reached them a moment ago.
        $other = new Campaign();
        $other->setName('Other campaign');
        $other->setSegment('champions');
        $this->entityManager->persist($other);

        $alreadySent = CampaignRecipient::create($other, $email);
        $alreadySent->markAsSent('message-id');
        $this->entityManager->persist($alreadySent);
        $this->entityManager->flush();

        $recipient = $this->recipient($email);

        $this->marketingMailer = $this->createMock(MarketingMailer::class);
        $this->marketingMailer->expects(self::never())->method('send');

        $this->handler()(new SendCampaignEmail($recipient));

        self::assertSame(CampaignRecipient::STATUS_SKIPPED, $recipient->getStatus());

        $this->entityManager->createQuery('DELETE AppBundle\Entity\Marketing\CampaignRecipient r WHERE r.campaign = :c')
            ->setParameter('c', $other)->execute();
        $this->entityManager->createQuery('DELETE AppBundle\Entity\Marketing\Campaign c WHERE c = :c')
            ->setParameter('c', $other)->execute();
    }

    public function testTheEmailCarriesTheCampaignsSubjectSenderAndBody(): void
    {
        $recipient = $this->recipient('send_test_content@example.com');

        $captured = null;
        $this->marketingMailer = $this->createMock(MarketingMailer::class);
        $this->marketingMailer->method('send')
            ->willReturnCallback(function (Email $email) use (&$captured) {
                $captured = $email;

                return true;
            });

        $this->handler()(new SendCampaignEmail($recipient));

        self::assertInstanceOf(Email::class, $captured);
        self::assertSame('Hello', $captured->getSubject());
        self::assertSame('<p>Hello</p>', $captured->getHtmlBody());
        self::assertSame('hello@example.com', $captured->getFrom()[0]->getAddress());
        self::assertSame('send_test_content@example.com', $captured->getTo()[0]->getAddress());
    }
}
