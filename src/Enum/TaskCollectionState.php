<?php

namespace AppBundle\Enum;

enum TaskCollectionState {
    case PENDING;
    case IN_DELIVERY;
    case DELIVERED;
    case FAILED;
    case CANCELLED;

    function toFontAwesome() {
        return match ($this) {
            TaskCollectionState::PENDING => 'clock-o',
            TaskCollectionState::IN_DELIVERY => 'bicycle',
            TaskCollectionState::DELIVERED => 'check',
            TaskCollectionState::FAILED => 'exclamation-triangle',
            TaskCollectionState::CANCELLED => 'times'
        };
    }

    function toLabel() {
        return match ($this) {
            TaskCollectionState::PENDING => 'delivery.state.pending',
            TaskCollectionState::IN_DELIVERY => 'delivery.state.in_delivery',
            TaskCollectionState::DELIVERED => 'delivery.state.delivered',
            TaskCollectionState::FAILED => 'delivery.state.failed',
            TaskCollectionState::CANCELLED => 'delivery.state.cancelled'
        };
    }

    function toColor() {
        return match ($this) {
            TaskCollectionState::PENDING => 'grey',
            TaskCollectionState::IN_DELIVERY => 'skyblue',
            TaskCollectionState::DELIVERED => 'green',
            TaskCollectionState::FAILED => 'orange',
            TaskCollectionState::CANCELLED => 'red'
        };
    }
}
