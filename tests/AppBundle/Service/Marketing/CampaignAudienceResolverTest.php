<?php

namespace Tests\AppBundle\Service\Marketing;

use AppBundle\Entity\Marketing\CampaignRecipientRepository;
use AppBundle\Entity\Marketing\EmailSuppressionRepository;
use AppBundle\Entity\OptinConsentRepository;
use AppBundle\Enum\Optin;
use AppBundle\Service\Marketing\CampaignAudienceResolver;
use AppBundle\Service\RfmSegmentCalculator;
use AppBundle\Service\SettingsManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class CampaignAudienceResolverTest extends TestCase
{
    use ProphecyTrait;

    private $rfmSegmentCalculator;
    private $suppressionRepository;
    private $optinConsentRepository;
    private $recipientRepository;
    private $settingsManager;
    private $resolver;

    protected function setUp(): void
    {
        $this->rfmSegmentCalculator = $this->prophesize(RfmSegmentCalculator::class);
        $this->suppressionRepository = $this->prophesize(EmailSuppressionRepository::class);
        $this->optinConsentRepository = $this->prophesize(OptinConsentRepository::class);
        $this->recipientRepository = $this->prophesize(CampaignRecipientRepository::class);
        $this->settingsManager = $this->prophesize(SettingsManager::class);
        $this->settingsManager->get(Argument::any())->willReturn(null);

        // Nothing excluded unless a test says so.
        $this->suppressionRepository->findSuppressed(Argument::any())->willReturn([]);
        $this->recipientRepository->findRecentlyEmailed(Argument::cetera())->willReturn([]);

        $this->resolver = new CampaignAudienceResolver(
            $this->rfmSegmentCalculator->reveal(),
            $this->suppressionRepository->reveal(),
            $this->optinConsentRepository->reveal(),
            $this->recipientRepository->reveal(),
            $this->settingsManager->reveal()
        );
    }

    private function givenRfmRows(array $rows): void
    {
        $this->rfmSegmentCalculator->computeRows()->willReturn($rows);
    }

    private function row(int $id, ?string $email, string $segment = 'champions'): array
    {
        return [
            'id' => $id,
            'email' => $email,
            'first_name' => 'First' . $id,
            'last_name' => 'Last' . $id,
            'segment' => $segment,
        ];
    }

    private function givenEveryoneConsented(int ...$ids): void
    {
        $this->optinConsentRepository
            ->findCustomerIdsWithConsent(Optin::MARKETING, Argument::any())
            ->willReturn(array_fill_keys($ids, true));
    }

    public function testResolvesOnlyTheRequestedSegment(): void
    {
        $this->givenRfmRows([
            $this->row(1, 'a@example.com', 'champions'),
            $this->row(2, 'b@example.com', 'at_risk'),
        ]);
        $this->givenEveryoneConsented(1, 2);

        $audience = $this->resolver->resolve('champions');

        self::assertSame(1, $audience->segmentSize);
        self::assertSame(['a@example.com'], $audience->getEmails());
    }

    /**
     * Consent is an allow-list: an account that was never asked has no row,
     * and must not be emailed. Getting this backwards is the difference
     * between a campaign and unsolicited mail.
     */
    public function testExcludesCustomersWhoNeverConsented(): void
    {
        $this->givenRfmRows([
            $this->row(1, 'consented@example.com'),
            $this->row(2, 'never-asked@example.com'),
        ]);
        $this->givenEveryoneConsented(1);

        $audience = $this->resolver->resolve('champions');

        self::assertSame(['consented@example.com'], $audience->getEmails());
        self::assertSame(1, $audience->excludedNoConsent);
    }

    public function testExcludesSuppressedAddresses(): void
    {
        $this->givenRfmRows([
            $this->row(1, 'fine@example.com'),
            $this->row(2, 'bounced@example.com'),
        ]);
        $this->givenEveryoneConsented(1, 2);
        $this->suppressionRepository->findSuppressed(Argument::any())
            ->willReturn(['bounced@example.com' => true]);

        $audience = $this->resolver->resolve('champions');

        self::assertSame(['fine@example.com'], $audience->getEmails());
        self::assertSame(1, $audience->excludedSuppressed);
    }

    public function testExcludesCustomersEmailedWithinTheFrequencyCap(): void
    {
        $this->givenRfmRows([
            $this->row(1, 'fresh@example.com'),
            $this->row(2, 'emailed-tuesday@example.com'),
        ]);
        $this->givenEveryoneConsented(1, 2);
        $this->recipientRepository->findRecentlyEmailed(Argument::cetera())
            ->willReturn(['emailed-tuesday@example.com' => true]);

        $audience = $this->resolver->resolve('champions');

        self::assertSame(['fresh@example.com'], $audience->getEmails());
        self::assertSame(1, $audience->excludedRecentlyEmailed);
    }

    /**
     * RFM rows come from orders, which a guest can place without ever having
     * an account.
     */
    public function testExcludesRowsWithNoAddress(): void
    {
        $this->givenRfmRows([
            $this->row(1, 'a@example.com'),
            $this->row(2, null),
            $this->row(3, ''),
        ]);
        $this->givenEveryoneConsented(1, 2, 3);

        $audience = $this->resolver->resolve('champions');

        self::assertSame(['a@example.com'], $audience->getEmails());
        self::assertSame(2, $audience->excludedNoEmail);
    }

    public function testAddressesAreLowercasedForMatching(): void
    {
        $this->givenRfmRows([$this->row(1, 'Mixed.Case@Example.COM')]);
        $this->givenEveryoneConsented(1);

        self::assertSame(['mixed.case@example.com'], $this->resolver->resolve('champions')->getEmails());
    }

    public function testCountsAddUp(): void
    {
        $this->givenRfmRows([
            $this->row(1, 'ok@example.com'),
            $this->row(2, 'bounced@example.com'),
            $this->row(3, 'no-consent@example.com'),
            $this->row(4, 'recent@example.com'),
            $this->row(5, null),
        ]);
        $this->givenEveryoneConsented(1, 2, 4, 5);
        $this->suppressionRepository->findSuppressed(Argument::any())->willReturn(['bounced@example.com' => true]);
        $this->recipientRepository->findRecentlyEmailed(Argument::cetera())->willReturn(['recent@example.com' => true]);

        $audience = $this->resolver->resolve('champions');

        self::assertSame(5, $audience->segmentSize);
        self::assertSame(1, $audience->count());
        self::assertSame(4, $audience->excludedTotal());
        self::assertSame($audience->segmentSize, $audience->count() + $audience->excludedTotal());
    }

    public function testFrequencyCapDefaultsToAWeek(): void
    {
        self::assertSame(7, $this->resolver->getFrequencyCapDays());

        $this->settingsManager->get('marketing_frequency_cap_days')->willReturn(30);
        self::assertSame(30, $this->resolver->getFrequencyCapDays());
    }

    /**
     * 0 is how an admin turns the cap off, and is distinct from the setting
     * being unset.
     */
    public function testFrequencyCapCanBeTurnedOff(): void
    {
        $this->settingsManager->get('marketing_frequency_cap_days')->willReturn(0);

        self::assertSame(0, $this->resolver->getFrequencyCapDays());
    }

    public function testSegmentWithNobodyInItResolvesToNothing(): void
    {
        $this->givenRfmRows([$this->row(1, 'a@example.com', 'at_risk')]);

        $audience = $this->resolver->resolve('champions');

        self::assertTrue($audience->isEmpty());
        self::assertSame(0, $audience->segmentSize);
    }
}
