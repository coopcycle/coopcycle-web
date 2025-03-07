<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\AcceptOrder;
use AppBundle\Domain\Order\Event;
use AppBundle\Exception\LoopeatInsufficientStockException;
use AppBundle\Validator\Constraints\LoopeatStock as AssertLoopeatStock;
use SimpleBus\Message\Recorder\RecordsMessages;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AcceptOrderHandler
{
    private $eventRecorder;

    public function __construct(RecordsMessages $eventRecorder, ValidatorInterface $validator)
    {
        $this->eventRecorder = $eventRecorder;
        $this->validator = $validator;
    }

    public function __invoke(AcceptOrder $command)
    {
        $order = $command->getOrder();

        $violations = $this->validator->validate($order->getItems(), new All([ new AssertLoopeatStock(true) ]));
        if (count($violations) > 0) {
            throw new LoopeatInsufficientStockException($violations);
        }

        $this->eventRecorder->record(new Event\OrderAccepted($order));
    }
}
