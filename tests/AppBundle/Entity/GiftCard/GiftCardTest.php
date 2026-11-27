<?php

namespace Tests\AppBundle\Entity\GiftCard;

use AppBundle\Entity\GiftCard\GiftCard;
use AppBundle\Entity\GiftCard\GiftCardTransaction;
use PHPUnit\Framework\TestCase;

class GiftCardTest extends TestCase
{
    private function card(int $amount = 5000): GiftCard
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('ABCD2345EFGH');
        $giftCard->setCurrencyCode('EUR');
        $giftCard->setInitialAmount($amount);

        return $giftCard;
    }

    public function testStartsPendingAndUnspendable()
    {
        $giftCard = $this->card();

        $this->assertSame(GiftCard::STATE_PENDING, $giftCard->getState());
        $this->assertSame(0, $giftCard->getBalance());
        $this->assertFalse($giftCard->isRedeemable());
    }

    public function testActivateMakesItSpendable()
    {
        $giftCard = $this->card();
        $giftCard->activate(5000);

        $this->assertSame(GiftCard::STATE_ACTIVE, $giftCard->getState());
        $this->assertSame(5000, $giftCard->getBalance());
        $this->assertTrue($giftCard->isRedeemable());
        $this->assertNotNull($giftCard->getIssuedAt());
    }

    public function testActivateTwiceIsRefused()
    {
        $giftCard = $this->card();
        $giftCard->activate(5000);

        // Guards against a replayed webhook minting the balance twice.
        $this->expectException(\LogicException::class);
        $giftCard->activate(5000);
    }

    public function testDebitDepletesAtZero()
    {
        $giftCard = $this->card();
        $giftCard->activate(5000);

        $giftCard->debit(2000);
        $this->assertSame(3000, $giftCard->getBalance());
        $this->assertSame(GiftCard::STATE_ACTIVE, $giftCard->getState());

        $giftCard->debit(3000);
        $this->assertSame(0, $giftCard->getBalance());
        $this->assertSame(GiftCard::STATE_DEPLETED, $giftCard->getState());
        $this->assertFalse($giftCard->isRedeemable());
    }

    public function testCannotOverspend()
    {
        $giftCard = $this->card();
        $giftCard->activate(5000);

        $this->expectException(\LogicException::class);
        $giftCard->debit(5001);
    }

    public function testCreditRevivesADepletedCard()
    {
        $giftCard = $this->card();
        $giftCard->activate(5000);
        $giftCard->debit(5000);

        $giftCard->credit(5000);

        $this->assertSame(5000, $giftCard->getBalance());
        $this->assertSame(GiftCard::STATE_ACTIVE, $giftCard->getState());
    }

    public function testExpiredCardRefusesCreditUntilRevived()
    {
        $giftCard = $this->card();
        $giftCard->activate(5000);
        $giftCard->expire();

        $this->assertSame(0, $giftCard->getBalance());
        $this->assertSame(GiftCard::STATE_EXPIRED, $giftCard->getState());

        try {
            $giftCard->credit(1000);
            $this->fail('An expired card should not quietly accept credit');
        } catch (\LogicException $e) {
            // Expected: the sweep already forfeited the balance, and putting
            // money back without saying so would contradict the ledger.
        }

        // The refund path revives it first, so a refund is never a silent
        // confiscation.
        $giftCard->revive(new \DateTime('+1 year'));
        $giftCard->credit(1000);

        $this->assertSame(1000, $giftCard->getBalance());
        $this->assertSame(GiftCard::STATE_ACTIVE, $giftCard->getState());
    }

    public function testFreezeAndUnfreezeKeepTheBalance()
    {
        $giftCard = $this->card();
        $giftCard->activate(5000);

        $giftCard->freeze();
        $this->assertSame(GiftCard::STATE_FROZEN, $giftCard->getState());
        $this->assertSame(5000, $giftCard->getBalance());
        $this->assertFalse($giftCard->isRedeemable());

        $giftCard->unfreeze();
        $this->assertSame(GiftCard::STATE_ACTIVE, $giftCard->getState());
        $this->assertTrue($giftCard->isRedeemable());
    }

    public function testExpiryDateIsRespected()
    {
        $giftCard = $this->card();
        $giftCard->activate(5000);

        $this->assertFalse($giftCard->hasExpired(), 'A card with no expiry never lapses');

        $giftCard->setExpiresAt(new \DateTime('-1 day'));
        $this->assertTrue($giftCard->hasExpired());
        $this->assertFalse($giftCard->isRedeemable());
    }

    /**
     * The invariant the whole feature rests on: the balance is only ever
     * what the ledger says it is.
     */
    public function testBalanceAlwaysMatchesTheLedger()
    {
        $giftCard = $this->card();

        $purchase = GiftCardTransaction::purchase($giftCard, 5000);
        $giftCard->activate(5000);

        $hold = GiftCardTransaction::hold(
            $giftCard,
            2000,
            $this->createMock(\Sylius\Component\Order\Model\OrderInterface::class),
            $this->createMock(\Sylius\Component\Payment\Model\PaymentInterface::class)
        );
        $giftCard->debit(2000);

        $this->assertSame(3000, $giftCard->getBalance());
        $this->assertSame(3000, $purchase->getAmount() + $hold->getAmount());

        $refund = GiftCardTransaction::refund($giftCard, 2000, $hold);
        $giftCard->credit(2000);

        $this->assertSame(5000, $giftCard->getBalance());
        $this->assertSame(
            5000,
            $purchase->getAmount() + $hold->getAmount() + $refund->getAmount()
        );
    }
}
