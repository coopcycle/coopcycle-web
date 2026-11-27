<?php

namespace Tests\AppBundle\Entity\GiftCard;

use AppBundle\Entity\GiftCard\GiftCard;
use AppBundle\Entity\GiftCard\GiftCardTransaction;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

class GiftCardTransactionTest extends TestCase
{
    private GiftCard $giftCard;

    public function setUp(): void
    {
        $this->giftCard = new GiftCard();
        $this->giftCard->setCode('ABCD2345EFGH');
        $this->giftCard->setCurrencyCode('EUR');
        $this->giftCard->setInitialAmount(5000);
    }

    public function testSignsAreConsistent()
    {
        $this->assertSame(5000, GiftCardTransaction::purchase($this->giftCard, 5000)->getAmount());
        $this->assertSame(-2000, GiftCardTransaction::expiry($this->giftCard, 2000)->getAmount());

        // Written as a positive amount by the caller, stored negative: a
        // plain SUM() over the ledger has to read as the balance.
        $this->assertSame(-2000, GiftCardTransaction::expiry($this->giftCard, -2000)->getAmount());
    }

    public function testHoldBecomesRedemptionWithoutMovingMoney()
    {
        $hold = GiftCardTransaction::hold(
            $this->giftCard,
            2000,
            $this->createMock(OrderInterface::class),
            $this->createMock(PaymentInterface::class),
            new \DateTime('+2 days')
        );

        $this->assertTrue($hold->isHold());
        $this->assertSame(-2000, $hold->getAmount());
        $this->assertNotNull($hold->getExpiresAt());

        $hold->convertToRedemption();

        $this->assertTrue($hold->isRedemption());
        $this->assertSame(-2000, $hold->getAmount(), 'Capturing must not move the money again');
        $this->assertNull($hold->getExpiresAt(), 'A captured hold can no longer go stale');
    }

    public function testOnlyAHoldCanBecomeARedemption()
    {
        $purchase = GiftCardTransaction::purchase($this->giftCard, 5000);

        $this->expectException(\LogicException::class);
        $purchase->convertToRedemption();
    }

    public function testRefundPointsAtWhatItReverses()
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);

        $hold = GiftCardTransaction::hold($this->giftCard, 2000, $order, $payment);
        $refund = GiftCardTransaction::refund($this->giftCard, 2000, $hold);

        $this->assertSame(2000, $refund->getAmount());
        $this->assertSame($hold, $refund->getParent());
        // Inherited, so a refund is always attributable to the same leg --
        // which is what makes releasing a hold idempotent.
        $this->assertSame($order, $refund->getOrder());
        $this->assertSame($payment, $refund->getPayment());
    }

    public function testEveryEntryIsAttachedToItsCard()
    {
        GiftCardTransaction::purchase($this->giftCard, 5000);

        $this->assertCount(1, $this->giftCard->getTransactions());
    }
}
