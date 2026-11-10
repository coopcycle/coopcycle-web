<?php

namespace Tests\AppBundle\Service\Marketing;

use AppBundle\Entity\Marketing\Campaign;
use AppBundle\Service\Marketing\CampaignEmailFactory;
use AppBundle\Service\Marketing\MarketingMailer;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Mime\Address;

class CampaignEmailFactoryTest extends TestCase
{
    use ProphecyTrait;

    private $marketingMailer;
    private $factory;

    protected function setUp(): void
    {
        $this->marketingMailer = $this->prophesize(MarketingMailer::class);
        $this->marketingMailer->getSenderAddress()
            ->willReturn(new Address('configured@example.com', 'Configured Sender'));

        $this->factory = new CampaignEmailFactory($this->marketingMailer->reveal());
    }

    private function campaign(): Campaign
    {
        $campaign = new Campaign();
        $campaign->setName('Test');
        $campaign->setSegment('at_risk');
        $campaign->setSubject('It has been a while');
        $campaign->setBodyHtml('<html><body>Come back</body></html>');

        return $campaign;
    }

    public function testBuildsTheMessageFromTheCampaign(): void
    {
        $email = $this->factory->create($this->campaign(), 'someone@example.com');

        self::assertSame('It has been a while', $email->getSubject());
        self::assertSame('<html><body>Come back</body></html>', $email->getHtmlBody());
        self::assertSame('someone@example.com', $email->getTo()[0]->getAddress());
    }

    /**
     * A sent campaign is shown and tested as it actually went out, not as it
     * would go out under today's settings.
     */
    public function testUsesTheSenderTheCampaignWentOutWith(): void
    {
        $campaign = $this->campaign();
        $campaign->setSenderEmail('went-out-as@example.com');
        $campaign->setSenderName('Old Name');

        $email = $this->factory->create($campaign, 'someone@example.com');

        self::assertSame('went-out-as@example.com', $email->getFrom()[0]->getAddress());
        self::assertSame('Old Name', $email->getFrom()[0]->getName());
    }

    /**
     * A draft hasn't got one yet -- it's snapshotted at send time -- so a
     * test send has to fall back to what is configured now.
     */
    public function testFallsBackToTheConfiguredSenderForADraft(): void
    {
        $email = $this->factory->create($this->campaign(), 'someone@example.com');

        self::assertSame('configured@example.com', $email->getFrom()[0]->getAddress());
        self::assertSame('Configured Sender', $email->getFrom()[0]->getName());
    }
}
