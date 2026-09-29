<?php

namespace AppBundle\Transporter;

use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Task;
use AppBundle\EventListener\Edifact\TransporterPodNotifier;
use Transporter\Interface\ReportGeneratorInterface;
use Transporter\TransporterImpl;
use Transporter\TransporterOptions;

class ReportFromCC {

    public function __construct(
        private TransporterPodNotifier $podNotifier
    ) { }

    /**
     * A LIV|CFM scheduled before POD|CFM existed has no proof reported, and
     * nothing will trigger one anymore. A no-op for the tasks already covered.
     *
     * @param array<EDIFACTMessage> $unsynced
     */
    public function scheduleMissingPods(array $unsynced): void
    {
        foreach ($unsynced as $message) {
            if ($message->getSubMessageType() !== 'LIV|CFM') {
                continue;
            }
            foreach ($message->getTasks() as $task) {
                $this->podNotifier->notify($task);
            }
        }
    }

    /**
     * The POD|CFM of a same shipment are sent as one event, as long as they fit
     * in the COM segments of an RSJ: the app uploads its proofs one request at a
     * time, but only the first POD|CFM closes the position.
     *
     * @param array<EDIFACTMessage> $messages
     * @return array<ReportGeneratorInterface>
     */
    public function generateReports(array $messages, TransporterOptions $opts): array
    {
        $reports = [];
        $pods = [];
        $podReports = [];

        foreach ($messages as $message) {
            if ($message->getSubMessageType() !== TransporterPodNotifier::SUB_MESSAGE_TYPE) {
                $reports[] = $this->generateReport($message, $opts);
                continue;
            }

            $reference = $message->getReference();
            if (isset($podReports[$reference])
                && count($pods[$reference]) + count($message->getPods()) <= TransporterPodNotifier::MAX_PODS) {
                $pods[$reference] = array_merge($pods[$reference], $message->getPods());
                $podReports[$reference]->setPods($pods[$reference]);
                continue;
            }

            $podReports[$reference] = $this->generateReport($message, $opts);
            $pods[$reference] = $message->getPods();
            $reports[] = $podReports[$reference];
        }

        return $reports;
    }

    public function generateReport(
        EDIFACTMessage $message,
        TransporterOptions $opts
    ): ReportGeneratorInterface {
        $impl = new TransporterImpl($opts->getTransporter());
        /** @var ReportGeneratorInterface $generator */
        $generator = new ($impl->reportGenerator)($opts);
        $generator->setDocID(strval($message->getId()));
        $generator->setReference($message->getReference());
        $generator->setReceipt($message->getReference());
        if ($message->getSubMessageType() === 'LIV|CFM') {
            $this->attachTaskPods($message);
        }
        if (!empty($message->getPods())) {
            $generator->setPods($message->getPods());
        }
        if (!is_null($message->getAppointment())) {
            $generator->setAppointment($message->getAppointment());
        }
        $generator->setDSJ($message->getCreatedAt());
        [$situation, $reason] = explode('|', $message->getSubMessageType());
        $generator->setSituation(constant("Transporter\Enum\ReportSituation::$situation"));
        $generator->setReason(constant("Transporter\Enum\ReportReason::$reason"));
        return $generator;

    }
    /**
     * Kept for the transporters that predate POD|CFM, which read the proofs off
     * the LIV|CFM. REPORT 3.1 treats these URLs as provisional: the POD|CFM is
     * still what closes the position.
     *
     * The pods are stored on the message to keep valid logs.
     */
    private function attachTaskPods(EDIFACTMessage $message): void
    {
        $pods = array_merge(
            $message->getPods(),
            ...$message->getTasks()->map(fn(Task $t) => $this->podNotifier->podUrls($t))->toArray()
        );

        $message->setPods(
            array_slice(array_values(array_unique($pods)), 0, TransporterPodNotifier::MAX_PODS)
        );
    }

    /**
     * @param array<ReportGeneratorInterface> $reports
     */
    public function buildSCONTR(
        array $reports,
        TransporterOptions $opts
    ): string
    {
        $impl = new TransporterImpl($opts->getTransporter());
        $interchange = new ($impl->interchange)($opts);
       foreach ($reports as $report) {
            $interchange->addGenerator($report);
        }

        return $interchange->generate();
    }
}
