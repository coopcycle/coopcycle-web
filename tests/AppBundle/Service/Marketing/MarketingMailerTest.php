<?php

namespace Tests\AppBundle\Service\Marketing;

use AppBundle\Entity\Marketing\EmailSuppressionRepository;
use AppBundle\Service\Marketing\MarketingMailer;
use AppBundle\Service\Marketing\MarketingMailerNotConfiguredException;
use AppBundle\Service\Marketing\PostmarkClient;
use AppBundle\Service\SettingsManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;

class MarketingMailerTest extends TestCase
{
    use ProphecyTrait;

    private $settingsManager;
    private $postmarkClient;
    private $suppressionRepository;
    private $mailer;

    protected function setUp(): void
    {
        $this->settingsManager = $this->prophesize(SettingsManager::class);
        $this->settingsManager->get(Argument::any())->willReturn(null);
        $this->settingsManager->get('postmark_server_token')->willReturn('a-token');
        $this->settingsManager->get('postmark_sender_email')->willReturn('hello@example.com');
        $this->settingsManager->get('brand_name')->willReturn('CoopCycle');

        $this->postmarkClient = $this->prophesize(PostmarkClient::class);
        $this->postmarkClient->isConfigured()->willReturn(true);
        $this->postmarkClient->getBroadcastStream()->willReturn('broadcast');

        $this->suppressionRepository = $this->prophesize(EmailSuppressionRepository::class);
        $this->suppressionRepository->isSuppressed(Argument::any())->willReturn(false);

        $this->mailer = new MarketingMailer(
            $this->settingsManager->reveal(),
            $this->postmarkClient->reveal(),
            $this->suppressionRepository->reveal(),
            $this->prophesize(LoggerInterface::class)->reveal()
        );
    }

    public function testIsConfiguredNeedsBothATokenAndASender(): void
    {
        self::assertTrue($this->mailer->isConfigured());

        $this->settingsManager->get('postmark_sender_email')->willReturn(null);
        self::assertFalse($this->mailer->isConfigured());
    }

    public function testSenderFallsBackToTheBrandNameForItsLabel(): void
    {
        $address = $this->mailer->getSenderAddress();

        self::assertSame('hello@example.com', $address->getAddress());
        self::assertSame('CoopCycle', $address->getName());
    }

    public function testSenderUsesTheConfiguredNameWhenSet(): void
    {
        $this->settingsManager->get('postmark_sender_name')->willReturn('CoopCycle News');

        self::assertSame('CoopCycle News', $this->mailer->getSenderAddress()->getName());
    }

    public function testRefusesToBuildASenderWithoutAnAddress(): void
    {
        $this->settingsManager->get('postmark_sender_email')->willReturn(null);

        $this->expectException(MarketingMailerNotConfiguredException::class);

        $this->mailer->getSenderAddress();
    }

    /**
     * The backstop: Postmark would drop these anyway, but a wrongly built
     * audience must not get as far as handing them over.
     */
    public function testDoesNotSendToASuppressedAddress(): void
    {
        $this->suppressionRepository->isSuppressed('gone@example.com')->willReturn(true);

        $email = (new Email())->to('gone@example.com')->subject('Hi')->text('Hi');

        self::assertFalse($this->mailer->send($email));
    }

    public function testRefusesToSendWithoutAToken(): void
    {
        $this->settingsManager->get('postmark_server_token')->willReturn(null);

        $email = (new Email())->to('someone@example.com')->subject('Hi')->text('Hi');

        $this->expectException(MarketingMailerNotConfiguredException::class);

        $this->mailer->send($email);
    }
}
