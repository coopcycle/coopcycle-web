<?php

namespace Tests\AppBundle\Service\Marketing;

use AppBundle\Entity\Marketing\EmailSuppression;
use AppBundle\Service\Marketing\EmailSuppressionManager;
use AppBundle\Service\Marketing\PostmarkSuppressionEventHandler;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

class PostmarkSuppressionEventHandlerTest extends TestCase
{
    use ProphecyTrait;

    private $suppressionManager;
    private $handler;

    protected function setUp(): void
    {
        $this->suppressionManager = $this->prophesize(EmailSuppressionManager::class);

        $this->handler = new PostmarkSuppressionEventHandler(
            $this->suppressionManager->reveal(),
            $this->prophesize(LoggerInterface::class)->reveal()
        );
    }

    public function testHardBounceIsSuppressed(): void
    {
        $this->suppressionManager
            ->suppress('a@example.com', 'broadcast', EmailSuppression::REASON_HARD_BOUNCE, Argument::type(\DateTime::class))
            ->shouldBeCalledOnce();

        self::assertTrue($this->handler->handle([
            'RecordType' => 'Bounce',
            'Type' => 'HardBounce',
            'Email' => 'a@example.com',
            'BouncedAt' => '2026-10-09T10:00:00Z',
            'Inactive' => true,
            'MessageStream' => 'broadcast',
        ]));
    }

    /**
     * A full mailbox is temporary. Postmark keeps delivering to the address
     * until it gives up, and only then marks it inactive -- suppressing on
     * the first soft bounce would lose a customer over a bad afternoon.
     */
    public function testSoftBounceThatDidNotDeactivateIsIgnored(): void
    {
        $this->suppressionManager->suppress(Argument::cetera())->shouldNotBeCalled();

        self::assertFalse($this->handler->handle([
            'RecordType' => 'Bounce',
            'Type' => 'SoftBounce',
            'Email' => 'a@example.com',
            'Inactive' => false,
            'MessageStream' => 'broadcast',
        ]));
    }

    public function testSpamComplaintIsSuppressed(): void
    {
        $this->suppressionManager
            ->suppress('a@example.com', 'broadcast', EmailSuppression::REASON_SPAM_COMPLAINT, Argument::any())
            ->shouldBeCalledOnce();

        self::assertTrue($this->handler->handle([
            'RecordType' => 'SpamComplaint',
            'Email' => 'a@example.com',
            'BouncedAt' => '2026-10-09T10:00:00Z',
            'MessageStream' => 'broadcast',
        ]));
    }

    /**
     * Postmark reports an unsubscribe and an admin-side suppression under the
     * same reason, telling them apart only by Origin.
     */
    public function testUnsubscribeIsRecordedAsSuch(): void
    {
        $this->suppressionManager
            ->suppress('a@example.com', 'broadcast', EmailSuppression::REASON_UNSUBSCRIBE, Argument::any())
            ->shouldBeCalledOnce();

        self::assertTrue($this->handler->handle([
            'RecordType' => 'SubscriptionChange',
            'MessageStream' => 'broadcast',
            'Recipient' => 'a@example.com',
            'SuppressSending' => true,
            'SuppressionReason' => 'ManualSuppression',
            'Origin' => 'Recipient',
            'ChangedAt' => '2026-10-09T10:00:00Z',
        ]));
    }

    public function testAdminSideSuppressionIsRecordedAsManual(): void
    {
        $this->suppressionManager
            ->suppress('a@example.com', 'broadcast', EmailSuppression::REASON_MANUAL, Argument::any())
            ->shouldBeCalledOnce();

        self::assertTrue($this->handler->handle([
            'RecordType' => 'SubscriptionChange',
            'MessageStream' => 'broadcast',
            'Recipient' => 'a@example.com',
            'SuppressSending' => true,
            'SuppressionReason' => 'ManualSuppression',
            'Origin' => 'Customer',
        ]));
    }

    /**
     * Resubscribing arrives on the same event with the flag flipped. Missing
     * it would leave someone who opted back in invisible to every future
     * audience.
     */
    public function testResubscribingClearsTheSuppression(): void
    {
        $this->suppressionManager->unsuppress('a@example.com', 'broadcast')->shouldBeCalledOnce();
        $this->suppressionManager->suppress(Argument::cetera())->shouldNotBeCalled();

        self::assertTrue($this->handler->handle([
            'RecordType' => 'SubscriptionChange',
            'MessageStream' => 'broadcast',
            'Recipient' => 'a@example.com',
            'SuppressSending' => false,
        ]));
    }

    /**
     * A stream with open/click tracking posts those here too.
     */
    public function testUnrelatedEventTypesAreIgnored(): void
    {
        $this->suppressionManager->suppress(Argument::cetera())->shouldNotBeCalled();
        $this->suppressionManager->unsuppress(Argument::cetera())->shouldNotBeCalled();

        self::assertFalse($this->handler->handle(['RecordType' => 'Open', 'Recipient' => 'a@example.com']));
        self::assertFalse($this->handler->handle([]));
    }

    public function testPayloadsMissingAnAddressAreIgnored(): void
    {
        $this->suppressionManager->suppress(Argument::cetera())->shouldNotBeCalled();

        self::assertFalse($this->handler->handle(['RecordType' => 'Bounce', 'Inactive' => true]));
        self::assertFalse($this->handler->handle(['RecordType' => 'SpamComplaint']));
        // "Email" is the wrong field for this event -- it carries "Recipient".
        self::assertFalse($this->handler->handle([
            'RecordType' => 'SubscriptionChange',
            'Email' => 'a@example.com',
            'SuppressSending' => true,
        ]));
    }
}
