<?php

namespace AppBundle\Transporter;

use AppBundle\Entity\Edifact\EDIFACTMessage;
use Transporter\Interface\ReportGeneratorInterface;
use Transporter\TransporterOptions;

class ReportFromCC {

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
