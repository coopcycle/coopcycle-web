<?php

namespace AppBundle\Transporter;

use AppBundle\Entity\Edifact\EDIFACTMessage;
use Transporter\TransporterOptions;
use Transporter\Transporters\DBSchenker\Generator\DBSchenkerReport;

class ReportFromCC {

    public function generateReport(
        EDIFACTMessage $message,
        TransporterOptions $opts
    ) {
        //TODO: Switch implementation on runtime
        $report = new DBSchenkerReport($opts);
        $report->setDocID(strval($message->getId()));
        $report->setReference($message->getReference());
        $report->setReceipt($message->getReference());
        if (!empty($message->getPods())) {
            $report->setPods($message->getPods());
        }
        if (!is_null($message->getAppointment())) {
            $report->setAppointment($message->getAppointment());
        }
        $report->setDSJ($message->getCreatedAt());
        [$situation, $reason] = explode('|', $message->getSubMessageType());
        $report->setSituation(constant("Transporter\Enum\ReportSituation::$situation"));
        $report->setReason(constant("Transporter\Enum\ReportReason::$reason"));
        return $report;

    }
}
