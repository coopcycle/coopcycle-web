<?php

namespace Tests\AppBundle\Service\GiftCard;

use AppBundle\Entity\GiftCard\GiftCard;
use AppBundle\Entity\GiftCard\GiftCardTransaction;
use AppBundle\Entity\GiftCard\GiftCardTransactionRepository;
use AppBundle\Service\GiftCard\GiftCardIssuer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * What the unit tests can't cover: that issuing really does take its lock,
 * write its rows and stay idempotent against the actual database.
 *
 * Idempotency is the point. Two things race to issue a card -- the purchase
 * page, which confirms the intent so the buyer sees their code immediately,
 * and Stripe's payment_intent.succeeded webhook, which catches the buyer who
 * closed the tab. Whichever arrives second must do nothing at all.
 */
class GiftCardIssuerIntegrationTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private array $giftCardIds = [];

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->giftCardIssuer = self::getContainer()->get(GiftCardIssuer::class);
        $this->transactionRepository = self::getContainer()->get(GiftCardTransactionRepository::class);
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();

        foreach ($this->giftCardIds as $id) {
            $giftCard = $this->entityManager->getRepository(GiftCard::class)->find($id);
            if (null !== $giftCard) {
                $this->entityManager->remove($giftCard);
            }
        }

        $this->entityManager->flush();

        parent::tearDown();
    }

    private function track(GiftCard $giftCard): GiftCard
    {
        $this->giftCardIds[] = $giftCard->getId();

        return $giftCard;
    }

    public function testPendingCardIsNotYetWorthAnything()
    {
        $giftCard = $this->track($this->giftCardIssuer->createPending(
            5000, 'EUR', null, 'buyer@coopcycle.org'
        ));

        $this->assertNotNull($giftCard->getId());
        $this->assertSame(GiftCard::STATE_PENDING, $giftCard->getState());
        $this->assertSame(0, $giftCard->getBalance());
        $this->assertFalse($giftCard->isRedeemable());
        $this->assertCount(0, $giftCard->getTransactions());
        // 12 characters from the unambiguous alphabet.
        $this->assertMatchesRegularExpression('/^[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{12}$/', $giftCard->getCode());
    }

    public function testIssuingWritesTheLedgerAndTheBackingCharge()
    {
        $giftCard = $this->track($this->giftCardIssuer->createPending(
            5000, 'EUR', null, 'buyer@coopcycle.org'
        ));

        $this->giftCardIssuer->issue($giftCard, 'ch_test_'.uniqid(), 5000);

        $this->entityManager->refresh($giftCard);

        $this->assertSame(GiftCard::STATE_ACTIVE, $giftCard->getState());
        $this->assertSame(5000, $giftCard->getBalance());
        $this->assertTrue($giftCard->isRedeemable());
        $this->assertNotNull($giftCard->getIssuedAt());

        // The expiry policy is applied at issue, not at purchase: a card is
        // dated from the moment it became worth something.
        $this->assertNotNull($giftCard->getExpiresAt(), 'Cards lapse by default');
        $this->assertSame(
            (new \DateTime('+12 months'))->format('Y-m-d'),
            $giftCard->getExpiresAt()->format('Y-m-d')
        );

        $this->assertCount(1, $giftCard->getTransactions());
        $this->assertSame(
            GiftCardTransaction::TYPE_PURCHASE,
            $giftCard->getTransactions()->first()->getType()
        );

        // The headroom redemptions will draw on.
        $this->assertCount(1, $giftCard->getPurchaseCharges());
        $this->assertSame(5000, $giftCard->getPurchaseCharges()->first()->getAvailableAmount());

        $this->assertSame(5000, $this->transactionRepository->getLedgerBalance($giftCard));
    }

    public function testIssuingTwiceMintsNothingExtra()
    {
        $giftCard = $this->track($this->giftCardIssuer->createPending(
            5000, 'EUR', null, 'buyer@coopcycle.org'
        ));

        $chargeId = 'ch_test_'.uniqid();

        $this->giftCardIssuer->issue($giftCard, $chargeId, 5000);
        // The webhook arrives after the purchase page already did this.
        $this->giftCardIssuer->issue($giftCard, $chargeId, 5000);

        $this->entityManager->refresh($giftCard);

        $this->assertSame(5000, $giftCard->getBalance(), 'The card must be worth what was paid, once');
        $this->assertCount(1, $giftCard->getTransactions());
        $this->assertCount(1, $giftCard->getPurchaseCharges());
        $this->assertSame(5000, $this->transactionRepository->getLedgerBalance($giftCard));
    }

    /**
     * What Stripe captured is what the card is worth -- that is the money
     * the co-op can actually pay restaurants out of.
     */
    public function testAPartialCaptureIssuesForWhatArrived()
    {
        $giftCard = $this->track($this->giftCardIssuer->createPending(
            5000, 'EUR', null, 'buyer@coopcycle.org'
        ));

        $this->giftCardIssuer->issue($giftCard, 'ch_test_'.uniqid(), 4000);

        $this->entityManager->refresh($giftCard);

        $this->assertSame(4000, $giftCard->getInitialAmount());
        $this->assertSame(4000, $giftCard->getBalance());
        $this->assertSame(4000, $giftCard->getPurchaseCharges()->first()->getAmount());
        $this->assertSame(4000, $this->transactionRepository->getLedgerBalance($giftCard));
    }

    public function testAGrantedCardIsLiveImmediatelyAndMarkedAsSuch()
    {
        $giftCard = $this->track($this->giftCardIssuer->issueGranted(
            2500, 'EUR', reason: 'Compensation for a late delivery'
        ));

        $this->assertSame(GiftCard::STATE_ACTIVE, $giftCard->getState());
        $this->assertSame(2500, $giftCard->getBalance());
        $this->assertSame(GiftCard::FUNDING_GRANTED, $giftCard->getFunding());
        // No charge behind it, so its redemptions settle untied -- the
        // co-op funding its own gesture.
        $this->assertNull($giftCard->getPurchaseChargeId());
        $this->assertCount(0, $giftCard->getPurchaseCharges());
        $this->assertSame(2500, $this->transactionRepository->getLedgerBalance($giftCard));
    }

    public function testCodesAreUnique()
    {
        $codes = [];

        for ($i = 0; $i < 5; $i++) {
            $codes[] = $this->track($this->giftCardIssuer->createPending(
                1000, 'EUR', null, 'buyer@coopcycle.org'
            ))->getCode();
        }

        $this->assertCount(5, array_unique($codes));
    }
}
