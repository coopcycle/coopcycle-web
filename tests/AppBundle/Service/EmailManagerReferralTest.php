<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\Referral\Referral;
use AppBundle\Entity\Referral\ReferralLevel;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Service\EmailManager;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Functional smoke test exercising the real Twig + MJML rendering pipeline
 * for the referral emails, to catch template/translation mistakes a fully
 * mocked unit test would miss.
 */
class EmailManagerReferralTest extends KernelTestCase
{
    use ProphecyTrait;

    private EmailManager $emailManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->emailManager = self::getContainer()->get(EmailManager::class);
    }

    public function testReferralWelcomeMessageRenders(): void
    {
        $coupon = $this->prophesize(\AppBundle\Sylius\Promotion\PromotionCouponInterface::class);
        $coupon->getCode()->willReturn('WELCOME123');

        $referral = new Referral();
        $referral->setReferrer(new Customer());
        $referral->setReferred(new Customer());
        $referral->setReferredWelcomeCoupon($coupon->reveal());

        $message = $this->emailManager->createReferralWelcomeMessage($referral);

        $this->assertNotEmpty($message->getSubject());
        $this->assertStringContainsString('WELCOME123', $message->getHtmlBody());
    }

    public function testReferralCompletedMessageForReferrerRenders(): void
    {
        $coupon = $this->prophesize(\AppBundle\Sylius\Promotion\PromotionCouponInterface::class);
        $coupon->getCode()->willReturn('REWARD123');

        $referral = new Referral();
        $referral->setReferrer(new Customer());
        $referral->setReferred(new Customer());
        $referral->setReferrerRewardCoupon($coupon->reveal());

        $message = $this->emailManager->createReferralCompletedMessageForReferrer($referral);

        $this->assertNotEmpty($message->getSubject());
        $this->assertStringContainsString('REWARD123', $message->getHtmlBody());
    }

    public function testReferralLevelUpMessageRenders(): void
    {
        $level = new ReferralLevel();
        $level->setName('gold');

        $message = $this->emailManager->createReferralLevelUpMessage(new Customer(), $level);

        $this->assertNotEmpty($message->getSubject());
        $this->assertStringContainsString('Gold', $message->getHtmlBody());
    }
}
