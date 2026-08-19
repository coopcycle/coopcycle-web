<?php

namespace AppBundle\Transporter;

use AppBundle\Entity\Edifact\EDIFACTMessage;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;
use Transporter\DTO\CommunicationMean;
use Transporter\DTO\Date;
use Transporter\DTO\Mesurement;
use Transporter\DTO\NameAndAddress;
use Transporter\DTO\Package;
use Transporter\DTO\Point;
use Transporter\Enum\INOVERTMessageType;
use Transporter\Enum\ReportReason;
use Transporter\Enum\ReportSituation;
use Transporter\Enum\TransporterName;
use Transporter\Transporter;

/**
 * Reads the original EDIFACT file an EDIFACTMessage was imported from (stored
 * on S3) and turns it into a plain, human-readable structure so administrators
 * can inspect the source data of a delivery without reading raw EDIFACT.
 */
class EdifactMessageParser
{
    public function __construct(
        private Filesystem $edifactFs,
        private LoggerInterface $transporterLogger,
    ) {
    }

    /**
     * Parses the EDIFACT file backing the given message and returns the
     * structured data for the point matching the message reference.
     *
     * @return array<string,mixed>|null Null when the file is missing, cannot
     *   be parsed, or holds no point matching the message reference.
     */
    public function parse(EDIFACTMessage $message): ?array
    {
        $filename = $message->getEdiMessage();
        if (empty($filename)) {
            return null;
        }

        if (!$this->edifactFs->fileExists($filename)) {
            $this->transporterLogger->warning(sprintf(
                'EDIFACT file "%s" not found on storage for message %d',
                $filename,
                $message->getId()
            ), ['transporter' => $message->getTransporter()]);
            return null;
        }

        $content = $this->edifactFs->read($filename);

        try {
            $transporter = TransporterName::from($message->getTransporter());
        } catch (\ValueError $e) {
            $this->transporterLogger->warning(sprintf(
                'Unknown transporter "%s" for message %d',
                $message->getTransporter(),
                $message->getId()
            ));
            return null;
        }

        try {
            [, $parsers] = Transporter::parse(
                $this->normalize($content),
                $transporter,
                $this->messageType($message)
            );
        } catch (\Throwable $e) {
            $this->transporterLogger->warning(sprintf(
                'Failed to parse EDIFACT file "%s" for message %d: %s',
                $filename,
                $message->getId(),
                $e->getMessage()
            ), ['transporter' => $message->getTransporter()]);
            return null;
        }

        $point = $this->findPoint($parsers, $message->getReference());
        if (is_null($point)) {
            return null;
        }

        return [
            'reference' => $message->getReference(),
            'transporter' => $message->getTransporter(),
            'messageType' => $message->getMessageType(),
            'direction' => $message->getDirection(),
            'createdAt' => $message->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'file' => $filename,
            'point' => $this->pointToArray($point),
            'raw' => $this->normalize($content),
        ];
    }

    /**
     * Builds a human-readable structure for an outbound REPORT message (data
     * we push back to the transporter about a delivery's outcome). Unlike
     * inbound messages, REPORT content isn't re-parsed via the transporter
     * library: the situation/reason/pods/appointment are read directly off
     * the entity, since that's exactly the data used to generate the report.
     *
     * @return array<string,mixed>
     */
    public function parseReport(EDIFACTMessage $message): array
    {
        $situation = null;
        $reason = null;
        if (!empty($message->getSubMessageType()) && str_contains($message->getSubMessageType(), '|')) {
            [$situation, $reason] = explode('|', $message->getSubMessageType());
        }

        $filename = $message->getEdiMessage();
        $raw = null;
        if (!empty($filename) && $this->edifactFs->fileExists($filename)) {
            $raw = $this->normalize($this->edifactFs->read($filename));
        }

        return [
            'reference' => $message->getReference(),
            'transporter' => $message->getTransporter(),
            'situation' => $situation,
            'situationLabel' => $situation ? (ReportSituation::tryFrom($situation)?->value ?? $situation) : null,
            'reason' => $reason,
            'reasonLabel' => $reason ? (ReportReason::tryFrom($reason)?->value ?? $reason) : null,
            'pods' => $message->getPods(),
            'appointment' => $message->getAppointment()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $message->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'syncedAt' => $message->getSyncedAt()?->format(\DateTimeInterface::ATOM),
            'file' => $filename,
            'raw' => $raw,
        ];
    }

    private function messageType(EDIFACTMessage $message): ?INOVERTMessageType
    {
        return match ($message->getMessageType()) {
            EDIFACTMessage::MESSAGE_TYPE_SCONTR => INOVERTMessageType::SCONTR,
            EDIFACTMessage::MESSAGE_TYPE_PICKUP => INOVERTMessageType::PICKUP,
            EDIFACTMessage::MESSAGE_TYPE_DISPOR => INOVERTMessageType::DISPOR,
            // Fall back to auto-detection for anything else (e.g. REPORT).
            default => null,
        };
    }

    /**
     * @param array<\Transporter\Interface\TransporterParserInterface> $parsers
     */
    private function findPoint(array $parsers, string $reference): ?Point
    {
        foreach ($parsers as $parser) {
            foreach ($parser->getTasks() as $point) {
                if ($point->getId() === $reference) {
                    return $point;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private function pointToArray(Point $point): array
    {
        return [
            'id' => $point->getId(),
            'type' => $point->getType()->name,
            'comments' => $point->getComments(),
            'namesAndAddresses' => array_map(
                fn(NameAndAddress $nad) => [
                    'type' => $nad->getType()->name,
                    'addressLabel' => $nad->getAddressLabel(),
                    'contactName' => $nad->getContactName(),
                    'contactSiret' => $nad->getContactSiret(),
                    'address' => $nad->getAddress(),
                    'latitude' => $nad->getLatitude(),
                    'longitude' => $nad->getLongitude(),
                    'communicationMeans' => array_map(
                        fn(CommunicationMean $c) => [
                            'type' => $c->getType()->name,
                            'value' => $c->getValue(),
                        ],
                        $nad->getCommunicationMeans()
                    ),
                ],
                $point->getNamesAndAddresses()
            ),
            'dates' => array_map(
                fn(Date $date) => [
                    'event' => $date->getEvent()->name,
                    'date' => $date->getDate()->format(\DateTimeInterface::ATOM),
                ],
                $point->getDates()
            ),
            'measurements' => array_map(
                fn(Mesurement $m) => [
                    'type' => $m->getType()->name,
                    'quantity' => $m->getQuantity(),
                    'unit' => $m->getUnit()->name,
                ],
                $point->getMesurements()
            ),
            'packages' => array_map(
                fn(Package $p) => [
                    'type' => $p->getType()->name,
                    'quantity' => $p->getQuantity(),
                ],
                $point->getPackages()
            ),
        ];
    }

    /**
     * Guards against invalid UTF-8 in the archived file so a single malformed
     * byte cannot abort JSON serialization of the parsed result.
     */
    private function normalize(string $content): string
    {
        return mb_convert_encoding($content, 'UTF-8', 'UTF-8');
    }
}
