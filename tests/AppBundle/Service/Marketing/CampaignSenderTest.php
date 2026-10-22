<?php

namespace Tests\AppBundle\Service\Marketing;

use AppBundle\Entity\Marketing\Campaign;
use AppBundle\Message\Marketing\SendCampaignEmail;
use AppBundle\Service\Marketing\CampaignAudience;
use AppBundle\Service\Marketing\CampaignAudienceResolver;
use AppBundle\Service\Marketing\CampaignNotSendableException;
use AppBundle\Service\Marketing\CampaignRecipientCandidate;
use AppBundle\Service\Marketing\CampaignSender;
use AppBundle\Service\Marketing\MarketingMailer;
use AppBundle\Service\Marketing\MarketingMailerNotConfiguredException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;

class CampaignSenderTest extends TestCase
{
    use ProphecyTrait;

    private $audienceResolver;
    private $marketingMailer;
    private $entityManager;
    private $messageBus;
    private $sender;

    protected function setUp(): void
    {
        $this->audienceResolver = $this->prophesize(CampaignAudienceResolver::class);

        $this->marketingMailer = $this->prophesize(MarketingMailer::class);
        $this->marketingMailer->isConfigured()->willReturn(true);
        $this->marketingMailer->getSenderAddress()
            ->willReturn(new Address('hello@example.com', 'CoopCycle'));

        // Recipients are persisted and flushed before being queued, so the
        // double has to hand out ids the way a real flush would.
        $nextId = 1;
        $persisted = [];
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->entityManager->persist(Argument::any())->will(function ($args) use (&$persisted) {
            $persisted[] = $args[0];
        });
        $this->entityManager->flush()->will(function () use (&$persisted, &$nextId) {
            foreach ($persisted as $entity) {
                $property = new \ReflectionProperty($entity, 'id');
                $property->setAccessible(true);
                if (null === $property->getValue($entity)) {
                    $property->setValue($entity, $nextId++);
                }
            }
            $persisted = [];
        });
        $this->entityManager->getReference(Argument::cetera())->willReturn(null);

        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->messageBus->dispatch(Argument::any())->will(fn ($args) => new Envelope($args[0]));

        $this->sender = new CampaignSender(
            $this->audienceResolver->reveal(),
            $this->marketingMailer->reveal(),
            $this->entityManager->reveal(),
            $this->messageBus->reveal(),
            new NullLogger()
        );
    }

    private function campaign(string $status = Campaign::STATUS_DRAFT): Campaign
    {
        $campaign = new Campaign();
        $campaign->setName('Test');
        $campaign->setSegment('champions');
        $campaign->setSubject('Hello');
        $campaign->setBodyHtml('<p>Hello</p>');
        $campaign->setStatus($status);

        return $campaign;
    }

    private function givenAudience(CampaignRecipientCandidate ...$candidates): void
    {
        $this->audienceResolver->resolve(Argument::cetera())
            ->willReturn(new CampaignAudience($candidates, count($candidates)));
    }

    public function testQueuesOneMessagePerRecipient(): void
    {
        $this->givenAudience(
            new CampaignRecipientCandidate(1, 'a@example.com'),
            new CampaignRecipientCandidate(2, 'b@example.com'),
        );

        $this->messageBus->dispatch(Argument::type(SendCampaignEmail::class))
            ->shouldBeCalledTimes(2)
            ->will(fn ($args) => new Envelope($args[0]));

        $campaign = $this->campaign();
        $audience = $this->sender->send($campaign);

        self::assertSame(2, $audience->count());
        self::assertSame(Campaign::STATUS_SENDING, $campaign->getStatus());
    }

    /**
     * Snapshotted at send time so the history still says who it came from
     * after an admin changes the sender settings.
     */
    public function testSnapshotsTheSenderOntoTheCampaign(): void
    {
        $this->givenAudience(new CampaignRecipientCandidate(1, 'a@example.com'));

        $campaign = $this->campaign();
        $this->sender->send($campaign);

        self::assertSame('hello@example.com', $campaign->getSenderEmail());
        self::assertSame('CoopCycle', $campaign->getSenderName());
    }

    /**
     * Nobody to email is a finished campaign, not one stuck at "sending"
     * forever waiting on workers that will never run.
     */
    public function testACampaignWithNoAudienceIsClosedImmediately(): void
    {
        $this->givenAudience();

        $this->messageBus->dispatch(Argument::any())->shouldNotBeCalled();

        $campaign = $this->campaign();
        $this->sender->send($campaign);

        self::assertSame(Campaign::STATUS_SENT, $campaign->getStatus());
        self::assertNotNull($campaign->getSentAt());
    }

    public function testRefusesACampaignThatHasAlreadyStarted(): void
    {
        $this->messageBus->dispatch(Argument::any())->shouldNotBeCalled();

        $this->expectException(CampaignNotSendableException::class);

        $this->sender->send($this->campaign(Campaign::STATUS_SENDING));
    }

    public function testRefusesACampaignWithNoSubjectOrBody(): void
    {
        $campaign = $this->campaign();
        $campaign->setSubject(null);

        $this->expectException(CampaignNotSendableException::class);

        $this->sender->send($campaign);
    }

    /**
     * Better to fail here than to queue a few hundred messages that will
     * each fail identically.
     */
    public function testRefusesToStartWhenPostmarkIsNotConfigured(): void
    {
        $this->marketingMailer->isConfigured()->willReturn(false);
        $this->audienceResolver->resolve(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(MarketingMailerNotConfiguredException::class);

        $this->sender->send($this->campaign());
    }
}
