<?php

declare(strict_types=1);

namespace AppBundle\Message;

final class RdcMessage
{
    /**
     * @param array $loPayload           Raw `lo` array. Re-parsed in the worker via `RdcApiServiceRequest::parse()` — the parse is deterministic and side-effect-free.
     * @param array $notificationMetadata Raw `notificationMetadata` from the webhook event. Used by the handler to compute the idempotency hash.
     */
    public function __construct(
        public readonly array $loPayload,
        public readonly string $loMember,
        public readonly string $loUri,
        public readonly ?int $loRevision,
        public readonly array $notificationMetadata,
    ) {}
}
