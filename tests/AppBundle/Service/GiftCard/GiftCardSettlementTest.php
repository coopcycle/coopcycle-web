<?php

namespace Tests\AppBundle\Service\GiftCard;

use AppBundle\Entity\Contract;
use AppBundle\Entity\GiftCard\GiftCard;
use AppBundle\Entity\GiftCard\GiftCardPurchaseCharge;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\OrderTransfer;
use AppBundle\Entity\OrderTransferRepository;
use AppBundle\Entity\StripeAccount;
use AppBundle\Service\GiftCard\GiftCardSettlement;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Stripe;

/**
 * The arithmetic that decides what a restaurant is paid on a gift-card
 * order, in integer cents.
 *
 * The worked example throughout: items 20.00, delivery 3.50, tip 1.00, so
 * the order totals 24.50. The co-op's fee comes to 8.00, leaving 16.50 as
 * the restaurant's share -- the same 16.50 it would get on an ordinary
 * order. That number not moving is the whole point of these tests.
 */
class GiftCardSettlementTest extends TestCase
{
    use ProphecyTrait;

    private const TOTAL = 2450;
    private const RESTAURANT_SHARE = 1650;

    public function setUp(): void
    {
        $this->orderTransferRepository = $this->prophesize(OrderTransferRepository::class);
        $this->stripeManager = $this->prophesize(StripeManager::class);
        $this->settingsManager = $this->prophesize(SettingsManager::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        $this->settingsManager->isStripeLivemode()->willReturn(false);
        $this->settingsManager->get('currency_code')->willReturn('EUR');

        $this->orderTransferRepository->hasSucceededTransfer(Argument::any())->willReturn(false);
        $this->orderTransferRepository->findOneByIdempotencyKey(Argument::cetera())->willReturn(null);

        // Persisting and flushing are incidental here; what matters is which
        // transfers go out and for how much.
        $this->entityManager->persist(Argument::any())->willReturn(null);
        $this->entityManager->flush()->willReturn(null);

        $this->settlement = new GiftCardSettlement(
            $this->orderTransferRepository->reveal(),
            $this->stripeManager->reveal(),
            $this->settingsManager->reveal(),
            $this->entityManager->reveal(),
            new NullLogger()
        );
    }

    private function createRestaurant(bool $paysStripeFee, ?string $stripeUserId = 'acct_123')
    {
        $contract = $this->prophesize(Contract::class);
        $contract->isRestaurantPaysStripeFee()->willReturn($paysStripeFee);

        $restaurant = $this->prophesize(LocalBusiness::class);
        $restaurant->getId()->willReturn(1);
        $restaurant->getContract()->willReturn($contract->reveal());

        if (null === $stripeUserId) {
            $restaurant->getStripeAccount(false)->willReturn(null);
        } else {
            $stripeAccount = $this->prophesize(StripeAccount::class);
            $stripeAccount->getStripeUserId()->willReturn($stripeUserId);
            $restaurant->getStripeAccount(false)->willReturn($stripeAccount->reveal());
        }

        return $restaurant;
    }

    private function createOrder(
        $restaurant,
        int $giftCardTotal,
        int $stripeFeeTotal = 0,
        ?GiftCard $giftCard = null
    ) {
        $order = $this->prophesize(OrderInterface::class);

        $order->getId()->willReturn(42);
        $order->getTotal()->willReturn(self::TOTAL);
        $order->hasGiftCardPayment()->willReturn(true);
        $order->getGiftCardTotal()->willReturn($giftCardTotal);
        $order->getRestaurant()->willReturn($restaurant->reveal());
        $order->getTransferAmount(Argument::any())->willReturn(self::RESTAURANT_SHARE);
        $order->getStripeFeeTotal()->willReturn($stripeFeeTotal);
        $order->getGiftCard()->willReturn($giftCard);
        $order->getPayments()->willReturn(new ArrayCollection());

        return $order;
    }

    private function expectTransfer(int $amount, ?string $source, string $id = 'tr_1')
    {
        $transfer = Stripe\Transfer::constructFrom(['id' => $id]);

        $this->stripeManager
            ->createTransfer(
                destination: 'acct_123',
                amount: $amount,
                currency: 'EUR',
                sourceTransaction: $source,
                transferGroup: 'order_42',
                idempotencyKey: Argument::type('string')
            )
            ->shouldBeCalled()
            ->willReturn($transfer);
    }

    private function giftCardWithCharge(int $amount, string $chargeId = 'ch_purchase'): GiftCard
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('ABCD2345EFGH');
        $giftCard->setCurrencyCode('EUR');
        $giftCard->setInitialAmount($amount);
        $giftCard->activate($amount);
        $giftCard->addPurchaseCharge(new GiftCardPurchaseCharge($giftCard, $chargeId, $amount, 'EUR'));

        return $giftCard;
    }

    /**
     * The co-op bears the Stripe fee, so the restaurant gets its full share.
     * Platform nets 2450 − 1650 − 31 = 769, which is the 800 fee less the 31
     * it paid Stripe: exactly what it nets on an ordinary order.
     */
    public function testPartialCoverageWhenTheCoopBearsTheStripeFee()
    {
        $restaurant = $this->createRestaurant(paysStripeFee: false);
        $giftCard = $this->giftCardWithCharge(2000);
        $order = $this->createOrder($restaurant, 2000, 31, $giftCard);

        // 450 of the 1650 comes from this order's own card charge...
        $this->expectTransfer(450, 'ch_complement', 'tr_complement');
        // ...and the remaining 1200 from the gift card's purchase charge.
        $this->expectTransfer(1200, 'ch_purchase', 'tr_purchase');

        $this->settlement->settle($order->reveal(), 'ch_complement', 450);
    }

    /**
     * The restaurant bears the Stripe fee, as the contract says, so 31 is
     * deducted: 1650 − 31 = 1619. Platform nets 2450 − 1619 − 31 = 800, the
     * full fee -- again, exactly as on an ordinary order.
     */
    public function testPartialCoverageWhenTheRestaurantBearsTheStripeFee()
    {
        $restaurant = $this->createRestaurant(paysStripeFee: true);
        $giftCard = $this->giftCardWithCharge(2000);
        $order = $this->createOrder($restaurant, 2000, 31, $giftCard);

        $this->expectTransfer(450, 'ch_complement', 'tr_complement');
        $this->expectTransfer(1169, 'ch_purchase', 'tr_purchase');

        $this->settlement->settle($order->reveal(), 'ch_complement', 450);
    }

    /**
     * No card was charged, so there is no Stripe fee to deduct even on a
     * contract where the restaurant would bear one -- the order genuinely
     * incurred none.
     */
    public function testFullCoverageDrawsEntirelyOnThePurchaseCharge()
    {
        $restaurant = $this->createRestaurant(paysStripeFee: true);
        $giftCard = $this->giftCardWithCharge(3000);
        $order = $this->createOrder($restaurant, self::TOTAL, 0, $giftCard);

        $this->expectTransfer(self::RESTAURANT_SHARE, 'ch_purchase');

        $this->settlement->settle($order->reveal());
    }

    /**
     * A card the co-op granted has no charge behind it, so there is nothing
     * to cite as a source. The transfer still goes out -- the restaurant is
     * owed it -- and comes off the platform balance, which is the co-op
     * funding its own gesture.
     */
    public function testGrantedCardTransfersWithNoSource()
    {
        $restaurant = $this->createRestaurant(paysStripeFee: false);

        $giftCard = new GiftCard();
        $giftCard->setCode('ABCD2345EFGH');
        $giftCard->setCurrencyCode('EUR');
        $giftCard->setInitialAmount(self::TOTAL);
        $giftCard->setFunding(GiftCard::FUNDING_GRANTED);
        $giftCard->activate(self::TOTAL);

        $order = $this->createOrder($restaurant, self::TOTAL, 0, $giftCard);

        $this->expectTransfer(self::RESTAURANT_SHARE, null);

        $this->settlement->settle($order->reveal());
    }

    /**
     * A purchase charge already partly spent on an earlier order only has
     * what is left to give.
     */
    public function testAPartlyConsumedPurchaseChargeOnlyGivesWhatIsLeft()
    {
        $restaurant = $this->createRestaurant(paysStripeFee: false);

        $giftCard = $this->giftCardWithCharge(3000);
        $giftCard->getPurchaseCharges()->first()->allocate(2400);

        $order = $this->createOrder($restaurant, self::TOTAL, 0, $giftCard);

        $this->expectTransfer(600, 'ch_purchase', 'tr_purchase');
        // Stripe would refuse anything more against that charge, so the
        // balance of what is owed goes out untied.
        $this->expectTransfer(1050, null, 'tr_untied');

        $this->settlement->settle($order->reveal());
    }

    /**
     * Previously this was a silent `continue` and the restaurant's takings
     * simply stayed with the platform.
     */
    public function testNoStripeAccountIsRecordedRatherThanSwallowed()
    {
        $restaurant = $this->createRestaurant(paysStripeFee: false, stripeUserId: null);
        $order = $this->createOrder($restaurant, 2000, 0, $this->giftCardWithCharge(2000));

        $this->stripeManager->createTransfer(Argument::cetera())->shouldNotBeCalled();

        $this->entityManager
            ->persist(Argument::that(fn ($t) =>
                $t instanceof OrderTransfer && OrderTransfer::STATE_BLOCKED_NO_ACCOUNT === $t->getState()))
            ->shouldBeCalled();

        $this->settlement->settle($order->reveal(), 'ch_complement', 450);
    }

    /**
     * If what was taken doesn't add up to the order, the amount owed isn't
     * trustworthy. Refuse rather than transfer a number that may not be
     * backed by money.
     */
    public function testAMismatchStopsTheSettlement()
    {
        $restaurant = $this->createRestaurant(paysStripeFee: false);
        $order = $this->createOrder($restaurant, 2000, 0, $this->giftCardWithCharge(2000));

        $this->stripeManager->createTransfer(Argument::cetera())->shouldNotBeCalled();

        $this->entityManager
            ->persist(Argument::that(fn ($t) =>
                $t instanceof OrderTransfer && OrderTransfer::STATE_MISMATCH === $t->getState()))
            ->shouldBeCalled();

        // 2000 held + 400 captured = 2400, but the order is 2450.
        $this->settlement->settle($order->reveal(), 'ch_complement', 400);
    }

    public function testAnAlreadySettledOrderIsLeftAlone()
    {
        $restaurant = $this->createRestaurant(paysStripeFee: false);
        $order = $this->createOrder($restaurant, 2000, 0, $this->giftCardWithCharge(2000));

        $this->orderTransferRepository->hasSucceededTransfer(Argument::any())->willReturn(true);
        $this->stripeManager->createTransfer(Argument::cetera())->shouldNotBeCalled();

        $this->settlement->settle($order->reveal(), 'ch_complement', 450);
    }
}
