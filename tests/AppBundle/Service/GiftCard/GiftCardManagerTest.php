<?php

namespace Tests\AppBundle\Service\GiftCard;

use AppBundle\Entity\GiftCard\GiftCard;
use AppBundle\Entity\GiftCard\GiftCardRepository;
use AppBundle\Entity\GiftCard\GiftCardTransaction;
use AppBundle\Entity\GiftCard\GiftCardTransactionRepository;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Service\GiftCard\GiftCardException;
use AppBundle\Service\GiftCard\GiftCardManager;
use AppBundle\Service\GiftCard\GiftCardStatus;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;

class GiftCardManagerTest extends TestCase
{
    use ProphecyTrait;

    private GiftCard $giftCard;

    public function setUp(): void
    {
        $this->giftCardRepository = $this->prophesize(GiftCardRepository::class);
        $this->transactionRepository = $this->prophesize(GiftCardTransactionRepository::class);
        $this->giftCardStatus = $this->prophesize(GiftCardStatus::class);
        $this->settingsManager = $this->prophesize(SettingsManager::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        $this->giftCardStatus->isActive()->willReturn(true);
        $this->giftCardStatus->resolveExpiresAt()->willReturn(null);
        $this->settingsManager->isStripeLivemode()->willReturn(false);
        $this->settingsManager->get('currency_code')->willReturn('EUR');

        $this->entityManager->persist(Argument::any())->willReturn(null);
        $this->entityManager->flush()->willReturn(null);
        // Run the closure for real: the point of these tests is what happens
        // inside the transaction.
        $this->entityManager
            ->wrapInTransaction(Argument::type('callable'))
            ->will(fn ($args) => $args[0]());

        $this->giftCard = new GiftCard();
        $this->giftCard->setCode('ABCD2345EFGH');
        $this->giftCard->setCurrencyCode('EUR');
        $this->giftCard->setInitialAmount(5000);
        $this->giftCard->activate(5000);

        // The manager re-reads the card by id under a write lock, so the
        // double needs one; a card only ever reaches it persisted.
        $id = new \ReflectionProperty(GiftCard::class, 'id');
        $id->setValue($this->giftCard, 1);

        $this->giftCardRepository->findOneByIdForUpdate(Argument::any())->willReturn($this->giftCard);
        $this->transactionRepository->findHoldForPayment(Argument::cetera())->willReturn(null);
        $this->transactionRepository->findRedemptionForPayment(Argument::cetera())->willReturn(null);
        $this->transactionRepository->hasRefundForPayment(Argument::cetera())->willReturn(false);

        $this->manager = new GiftCardManager(
            $this->giftCardRepository->reveal(),
            $this->transactionRepository->reveal(),
            $this->giftCardStatus->reveal(),
            $this->settingsManager->reveal(),
            $this->entityManager->reveal(),
            new NullLogger()
        );
    }

    private function createOrder(int $total, ?GiftCard $giftCard = null, bool $multiVendor = false)
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->getId()->willReturn(42);
        $order->getTotal()->willReturn($total);
        $order->getGiftCard()->willReturn($giftCard ?? $this->giftCard);
        $order->isMultiVendor()->willReturn($multiVendor);
        $order->getRestaurant()->willReturn(null);
        $order->getPayments()->willReturn(new ArrayCollection());

        return $order;
    }

    private function createPayment($order, int $amount): Payment
    {
        $payment = new Payment();
        $payment->setAmount($amount);
        $payment->setCurrencyCode('EUR');
        $payment->setOrder($order);

        return $payment;
    }

    public function testMaxAmountIsCappedByBothBalanceAndTotal()
    {
        $this->assertSame(2450, $this->manager->getMaxAmount($this->createOrder(2450)->reveal()));
        $this->assertSame(5000, $this->manager->getMaxAmount($this->createOrder(8000)->reveal()));
    }

    public function testMaxAmountIsZeroWhenTheFeatureIsOff()
    {
        $this->giftCardStatus->isActive()->willReturn(false);

        $this->assertSame(0, $this->manager->getMaxAmount($this->createOrder(2450)->reveal()));
    }

    /**
     * Never throws: this runs on every cart recalculation, and a card that
     * has quietly become unusable should leave an ordinary order to pay for,
     * not a broken basket.
     */
    public function testMaxAmountSwallowsAnUnusableCard()
    {
        $this->giftCard->expire();

        $this->assertSame(0, $this->manager->getMaxAmount($this->createOrder(2450)->reveal()));
    }

    /**
     * Otherwise the card attaches, the order shows it, getMaxAmount() returns
     * nothing, and the customer pays full price for an order that claims to
     * have a gift card on it.
     */
    public function testACodeIsRefusedWhileTheFeatureIsOff()
    {
        $this->giftCardStatus->isActive()->willReturn(false);

        try {
            $this->manager->findForOrder('ABCD2345EFGH', $this->createOrder(2450)->reveal());
            $this->fail('A code must not attach while gift cards are turned off');
        } catch (GiftCardException $e) {
            $this->assertSame(GiftCardException::REASON_NOT_FOUND, $e->getReason());
        }
    }

    public function testReserveTakesTheMoneyAndWritesAHold()
    {
        $order = $this->createOrder(2450)->reveal();
        $payment = $this->createPayment($order, 2000);

        $hold = $this->manager->reserve($payment);

        $this->assertTrue($hold->isHold());
        $this->assertSame(-2000, $hold->getAmount());
        $this->assertSame(3000, $this->giftCard->getBalance());
        $this->assertNotNull($hold->getExpiresAt(), 'A hold must be able to go stale');
    }

    public function testReserveRefusesWhenTheBalanceMoved()
    {
        $order = $this->createOrder(2450)->reveal();
        $payment = $this->createPayment($order, 6000);

        try {
            $this->manager->reserve($payment);
            $this->fail('Reserving more than the balance should fail');
        } catch (GiftCardException $e) {
            $this->assertSame(GiftCardException::REASON_INSUFFICIENT_BALANCE, $e->getReason());
        }

        $this->assertSame(5000, $this->giftCard->getBalance(), 'A failed hold must not touch the balance');
    }

    public function testReserveIsIdempotentPerLeg()
    {
        $order = $this->createOrder(2450)->reveal();
        $payment = $this->createPayment($order, 2000);

        $hold = $this->manager->reserve($payment);

        // A replayed checkout finds the existing hold.
        $this->transactionRepository->findHoldForPayment(Argument::cetera())->willReturn($hold);

        $again = $this->manager->reserve($payment);

        $this->assertSame($hold, $again);
        $this->assertSame(3000, $this->giftCard->getBalance(), 'The money must only leave once');
    }

    public function testCaptureConvertsTheHoldWithoutMovingMoney()
    {
        $order = $this->createOrder(2450)->reveal();
        $payment = $this->createPayment($order, 2000);

        $hold = $this->manager->reserve($payment);
        $this->transactionRepository->findHoldForPayment(Argument::cetera())->willReturn($hold);

        $redemption = $this->manager->capture($payment);

        $this->assertTrue($redemption->isRedemption());
        $this->assertSame(3000, $this->giftCard->getBalance());
    }

    public function testReleasePutsTheMoneyBack()
    {
        $order = $this->createOrder(2450)->reveal();
        $payment = $this->createPayment($order, 2000);

        $hold = $this->manager->reserve($payment);
        $this->transactionRepository->findHoldForPayment(Argument::cetera())->willReturn($hold);

        $refund = $this->manager->release($payment);

        $this->assertTrue($refund->isRefund());
        $this->assertSame(2000, $refund->getAmount());
        $this->assertSame(5000, $this->giftCard->getBalance());
    }

    /**
     * Cancellation events get redelivered. Without this guard a replay would
     * credit the card again, and the customer would end up with more gift
     * card than they paid for.
     */
    public function testReleaseIsANoOpWhenAlreadyReleased()
    {
        $order = $this->createOrder(2450)->reveal();
        $payment = $this->createPayment($order, 2000);

        $this->manager->reserve($payment);
        $this->transactionRepository->hasRefundForPayment(Argument::cetera())->willReturn(true);

        $this->assertNull($this->manager->release($payment));
        $this->assertSame(3000, $this->giftCard->getBalance());
    }

    public function testReleaseRevivesACardThatLapsedInTheMeantime()
    {
        $order = $this->createOrder(2450)->reveal();
        $payment = $this->createPayment($order, 2000);

        $hold = $this->manager->reserve($payment);
        $this->transactionRepository->findHoldForPayment(Argument::cetera())->willReturn($hold);

        $this->giftCard->expire();
        $this->assertSame(GiftCard::STATE_EXPIRED, $this->giftCard->getState());

        $this->manager->release($payment);

        // Refunding onto a dead card would be a confiscation with extra steps.
        $this->assertSame(GiftCard::STATE_ACTIVE, $this->giftCard->getState());
        $this->assertSame(2000, $this->giftCard->getBalance());
    }

    /**
     * A failed split checkout releases the hold, and the customer tries
     * again. The retry has to take the money afresh.
     *
     * The ledger is append-only, so the released hold row is still there.
     * If the repository handed it back as though it were live, the order
     * would carry a gift-card leg that had never actually been debited --
     * the restaurant paid, the card untouched.
     */
    public function testARetryAfterAReleaseTakesTheMoneyAgain()
    {
        $order = $this->createOrder(2450)->reveal();
        $payment = $this->createPayment($order, 2000);

        $hold = $this->manager->reserve($payment);
        $this->assertSame(3000, $this->giftCard->getBalance());

        $this->transactionRepository->findHoldForPayment(Argument::cetera())->willReturn($hold);
        $this->manager->release($payment);
        $this->assertSame(5000, $this->giftCard->getBalance());

        // What the repository does once the hold has been reversed.
        $this->transactionRepository->findHoldForPayment(Argument::cetera())->willReturn(null);
        $this->transactionRepository->hasRefundForPayment(Argument::cetera())->willReturn(false);

        $this->manager->reserve($payment);

        $this->assertSame(3000, $this->giftCard->getBalance(), 'The retry must debit the card again');
    }

    public function testAHubOrderIsRefused()
    {
        $order = $this->createOrder(2450, multiVendor: true);

        try {
            $this->manager->assertApplicable($this->giftCard, $order->reveal());
            $this->fail('A gift card must not be usable on a hub order');
        } catch (GiftCardException $e) {
            $this->assertSame(GiftCardException::REASON_MULTI_VENDOR, $e->getReason());
        }
    }

    public function testAScopedCardIsRefusedElsewhere()
    {
        $theirs = $this->prophesize(LocalBusiness::class);
        $theirs->getId()->willReturn(1);
        $theirs->getName()->willReturn('Chez Machin');

        $ours = $this->prophesize(LocalBusiness::class);
        $ours->getId()->willReturn(2);

        $this->giftCard->setRestaurant($theirs->reveal());

        $order = $this->createOrder(2450);
        $order->getRestaurant()->willReturn($ours->reveal());

        try {
            $this->manager->assertApplicable($this->giftCard, $order->reveal());
            $this->fail('A scoped card must not work at another restaurant');
        } catch (GiftCardException $e) {
            $this->assertSame(GiftCardException::REASON_WRONG_RESTAURANT, $e->getReason());
            $this->assertSame(['restaurant' => 'Chez Machin'], $e->getContext());
        }
    }

    /**
     * A test-mode charge is not a valid source for a live transfer, so the
     * two modes must never mix.
     */
    public function testACardFromTheOtherStripeModeIsRefused()
    {
        $this->giftCard->setLivemode(true);

        try {
            $this->manager->assertApplicable($this->giftCard, $this->createOrder(2450)->reveal());
            $this->fail('A livemode card must not be usable in test mode');
        } catch (GiftCardException $e) {
            $this->assertSame(GiftCardException::REASON_LIVEMODE_MISMATCH, $e->getReason());
        }
    }

    public function testCombiningWithAMealVoucherIsRefused()
    {
        try {
            $this->manager->assertApplicable($this->giftCard, $this->createOrder(2450)->reveal(), 'EDENRED');
            $this->fail('Gift cards and meal vouchers must not be combined');
        } catch (GiftCardException $e) {
            $this->assertSame(GiftCardException::REASON_MEAL_VOUCHER, $e->getReason());
        }
    }
}
