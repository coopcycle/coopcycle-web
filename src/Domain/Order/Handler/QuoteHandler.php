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

    public function __construct(
        RecordsMessages $eventRecorder,
        OrderNumberAssignerInterface $orderNumberAssigner)
    {
        $this->eventRecorder = $eventRecorder;
        $this->orderNumberAssigner = $orderNumberAssigner;
    }

    public function __invoke(Quote $command)
    {
        $order = $command->getOrder();

        $this->orderNumberAssigner->assignNumber($order);

        $this->eventRecorder->record(new Event\CheckoutSucceeded($order));
    }
}
