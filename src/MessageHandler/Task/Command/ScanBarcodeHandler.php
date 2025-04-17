<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Domain\Task\Event;
use AppBundle\Message\Task\Command\ScanBarcode;
use SimpleBus\Message\Recorder\RecordsMessages;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'commandnew.bus')]
class ScanBarcodeHandler
{
    public function __construct(
        private RecordsMessages $eventRecorder
    )
    { }

    public function __invoke(ScanBarcode $command): void
    {
        $task = $command->getTask();

        $this->eventRecorder->record(new Event\TaskBarcodeScanned($task));


        // $edi = $task->getImportMessage();
        // if (!is_null($edi)) {
        //     //TODO: Send edifact message when scanned
        //
        //     $report = new EDIFACTMessage();
        //     $report->setDirection(EDIFACTMessage::DIRECTION_OUTBOUND);
        //     $report->setMessageType(EDIFACTMessage::MESSAGE_TYPE_REPORT);
        //     $report->setTransporter($edi->getTransporter());
        //     $report->setReference($edi->getReference());
        //     $task->addEdifactMessage($report);
        //
        //     $this->doctrine->persist($report);
        //     $this->doctrine->persist($task);
        //
        //     $this->logger->info(sprintf("[%s] EDIFACT report scan event for task: %d", __CLASS__, $task->getId()));
        // }

    }
}
