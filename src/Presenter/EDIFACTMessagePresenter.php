<?php

namespace AppBundle\Presenter;

use AppBundle\Entity\Edifact\EDIFACTMessage;

//TODO: Add translations
class EDIFACTMessagePresenter {

    public static function toTimeline(EDIFACTMessage $message): array {
        return [
            'synced' => $message->getSyncedAt() !== null,
            'title' => Self::messageToText($message),
            'icon' => Self::messageToIcon($message),
            'color' => Self::messageToColor($message),
            'date' => $message->getCreatedAt(),
            'edi' => $message->getEdiMessage(),
            'pods' => $message->getPods(),
            'description' => Self::subMessageTypeToI18n($message),
        ];
    }

    private static function messageToText(EDIFACTMessage $message): string {
        $ret = match ($message->getMessageType()) {
            EDIFACTMessage::MESSAGE_TYPE_SCONTR => "Tâche importée depuis le transporteur",
            EDIFACTMessage::MESSAGE_TYPE_REPORT => "Rapport de l'état de la tâche",
            default => "Unknown type",
        };

        if ($message->getMessageType() === EDIFACTMessage::MESSAGE_TYPE_REPORT) {
            if ($message->getSyncedAt() === null) {
                $ret .= " généré";
            } else {
                $ret .= " envoyé";
            }
        }

        return $ret;
    }

    private static function messageToIcon(EDIFACTMessage $message): string {
        return match ($message->getDirection()) {
            EDIFACTMessage::DIRECTION_INBOUND => "fa-arrow-down",
            EDIFACTMessage::DIRECTION_OUTBOUND => match ($message->getSyncedAt()) {
                null => "fa-circle",
                default => "fa-arrow-up",
            },
            default => "fa-question",
        };
    }

    //TODO: Improve this to a more generic way
    //Maybe use the EDIFACT's task to get if there is an incident
    private static function messageToColor(EDIFACTMessage $message): string {
        return match ($message->getSubMessageType()) {
            'LIV|CFM' => match ($message->getSyncedAt()) {
                null => 'primary',
                default => 'success',
            },
            default => 'default',
        };
    }

    private static function subMessageTypeToI18n(EDIFACTMessage $message): ?string {
        return match ($message->getSubMessageType()) {
            'LIV|CFM' => "delivery.failure_reason.transporter.state.liv",
            'AAR|CFM' => "delivery.failure_reason.transporter.state.aar",
            'MLV|CFM' => "delivery.failure_reason.transporter.state.mlv",
            default => null,
        };
    }
}
