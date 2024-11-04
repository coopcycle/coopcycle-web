<?php

namespace AppBundle\Domain\Task\Handler;

use AppBundle\Domain\Task\Command\ScanBarcode;
use AppBundle\Domain\Task\Event;
use SimpleBus\Message\Recorder\RecordsMessages;

class ScanBarcodeHandler
{
    public function __construct(
        // private EntityManager $doctrine,
        private RecordsMessages $eventRecorder,
        // private LoggerInterface $logger
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
