<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\Quote;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTimeHelper;
use SimpleBus\Message\Recorder\RecordsMessages;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;

class QuoteHandler
{
    private $eventRecorder;
    private $orderNumberAssigner;
    private $orderTimeHelper;

    public function __construct(
        RecordsMessages $eventRecorder,
        OrderNumberAssignerInterface $orderNumberAssigner,
        OrderTimeHelper $orderTimeHelper)
    {
        $this->eventRecorder = $eventRecorder;
        $this->orderNumberAssigner = $orderNumberAssigner;
        $this->orderTimeHelper = $orderTimeHelper;
    }

    private function setShippingDate(OrderInterface $order)
    {
        if (null === $order->getShippedAt()) {
            $asap = $this->orderTimeHelper->getAsap($order);
            $order->setShippedAt(new \DateTime($asap));
        }
    }

    public function __invoke(Quote $command)
    {
        $order = $command->getOrder();

        $this->orderNumberAssigner->assignNumber($order);
        // FIXME
        // We shouldn't auto-assign a date when it is a quote
        // Keeping this until it is possible to choose an arbitrary date
        // https://github.com/coopcycle/coopcycle-web/issues/698
        $this->setShippingDate($order);
        $this->eventRecorder->record(new Event\CheckoutSucceeded($order));
    }
}
