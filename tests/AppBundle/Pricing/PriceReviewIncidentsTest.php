<?php

declare(strict_types=1);

namespace Tests\AppBundle\Pricing;

use AppBundle\Action\Incident\CreateIncident;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Task;
use AppBundle\Pricing\PriceReviewIncidents;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class PriceReviewIncidentsTest extends TestCase
{
    use ProphecyTrait;

    private $entityManager;
    private $createIncident;

    private PriceReviewIncidents $priceReviewIncidents;

    public function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->entityManager->isOpen()->willReturn(true);
        $this->entityManager->flush()->will(function () {});

        $this->createIncident = $this->prophesize(CreateIncident::class);

        $translator = $this->prophesize(TranslatorInterface::class);
        $translator
            ->trans('form.delivery.price.missing.incident', Argument::type('array'))
            ->willReturn('Price to be reviewed');

        $security = $this->prophesize(Security::class);
        $security->getUser()->willReturn(null);

        $this->priceReviewIncidents = new PriceReviewIncidents(
            $this->entityManager->reveal(),
            $translator->reveal(),
            $security->reveal(),
            $this->createIncident->reveal(),
            new NullLogger()
        );
    }

    private function createOrder(?int $id, ?int $pickupId): OrderInterface
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->getId()->willReturn($id);
        $order->getNumber()->willReturn('ABC');

        if (null === $pickupId) {
            $order->getDelivery()->willReturn(null);
        } else {
            $pickup = $this->prophesize(Task::class);
            $pickup->getId()->willReturn($pickupId);

            $delivery = $this->prophesize(Delivery::class);
            $delivery->getPickup()->willReturn($pickup->reveal());

            $order->getDelivery()->willReturn($delivery->reveal());
        }

        return $order->reveal();
    }

    public function testCreatePendingReportsAnIncidentToReviewThePrice()
    {
        $this->priceReviewIncidents->schedule($this->createOrder(1, 2));

        $this->createIncident
            ->__invoke(
                Argument::that(function (Incident $incident) {
                    return 'PRICE_REVIEW_NEEDED' === $incident->getFailureReasonCode()
                        && 2 === $incident->getTask()->getId();
                }),
                null
            )
            ->shouldBeCalledTimes(1);

        // The task:incident-reported event is only written on flush
        $this->entityManager->flush()->shouldBeCalled();

        $this->priceReviewIncidents->createPending();
    }

    /**
     * A quote, or a request that failed before the flush: there is no order to
     * review, and no task to attach an incident to
     */
    public function testCreatePendingSkipsAnOrderThatWasNeverWritten()
    {
        $this->priceReviewIncidents->schedule($this->createOrder(null, null));

        $this->createIncident->__invoke(Argument::cetera())->shouldNotBeCalled();

        $this->priceReviewIncidents->createPending();
    }

    public function testCreatePendingDoesNotReportTheSameOrderTwice()
    {
        $order = $this->createOrder(1, 2);

        $this->priceReviewIncidents->schedule($order);
        $this->priceReviewIncidents->schedule($order);

        $this->createIncident->__invoke(Argument::cetera())->shouldBeCalledTimes(1);

        $this->priceReviewIncidents->createPending();
        // Drained: a second pass does not report it again
        $this->priceReviewIncidents->createPending();
    }

    public function testDiscardPendingDropsTheScheduledOrders()
    {
        $this->priceReviewIncidents->schedule($this->createOrder(1, 2));
        $this->priceReviewIncidents->discardPending();

        $this->createIncident->__invoke(Argument::cetera())->shouldNotBeCalled();

        $this->priceReviewIncidents->createPending();
    }

    public function testCreatePendingDoesNothingWhenTheEntityManagerIsClosed()
    {
        $this->entityManager->isOpen()->willReturn(false);

        $this->priceReviewIncidents->schedule($this->createOrder(1, 2));

        $this->createIncident->__invoke(Argument::cetera())->shouldNotBeCalled();

        $this->priceReviewIncidents->createPending();
    }
}
