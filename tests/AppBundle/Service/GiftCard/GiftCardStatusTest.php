<?php

namespace Tests\AppBundle\Service\GiftCard;

use AppBundle\Service\GiftCard\GiftCardStatus;
use AppBundle\Service\SettingsManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * The expiry policy, which is the one setting here with money and consumer
 * law attached to it.
 */
class GiftCardStatusTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->settingsManager = $this->prophesize(SettingsManager::class);
        $this->settingsManager->get(Argument::type('string'))->willReturn(null);
        $this->settingsManager->getBoolean(Argument::type('string'))->willReturn(true);
    }

    private function status(): GiftCardStatus
    {
        return new GiftCardStatus(true, $this->settingsManager->reveal());
    }

    /**
     * An instance that configures nothing sells cards that last a year.
     */
    public function testCardsLapseAfterAYearByDefault()
    {
        $status = $this->status();

        $this->assertSame(GiftCardStatus::EXPIRY_POLICY_FORFEIT, $status->getExpiryPolicy());
        $this->assertSame(12, $status->getValidityMonths());

        $expiresAt = $status->resolveExpiresAt(new \DateTime('2026-01-15 10:00:00'));

        $this->assertNotNull($expiresAt);
        $this->assertSame('2027-01-15', $expiresAt->format('Y-m-d'));
    }

    /**
     * The escape hatch for jurisdictions that restrict or forbid forfeiting
     * a balance.
     */
    public function testNeverPolicyIssuesCardsWithNoDeadline()
    {
        $this->settingsManager->get('gift_card_expiry_policy')
            ->willReturn(GiftCardStatus::EXPIRY_POLICY_NEVER);

        $status = $this->status();

        $this->assertSame(0, $status->getValidityMonths());
        $this->assertNull($status->resolveExpiresAt());
    }

    public function testAConfiguredDurationWins()
    {
        $this->settingsManager->get('gift_card_validity_months')->willReturn('24');

        $this->assertSame(24, $this->status()->getValidityMonths());
    }

    /**
     * "Expire, but after zero months" is a misconfiguration, not a request
     * for cards that never lapse -- the policy is how you ask for those.
     */
    public function testZeroMonthsFallsBackToTheDefaultRatherThanNeverExpiring()
    {
        $this->settingsManager->get('gift_card_validity_months')->willReturn('0');

        $this->assertSame(12, $this->status()->getValidityMonths());
        $this->assertNotNull($this->status()->resolveExpiresAt());
    }
}
