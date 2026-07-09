<?php

namespace AppBundle\Integration\Rdc\Api;

class RdcException extends \RuntimeException
{
    public const ERROR_REQUEST_FAILED = 1;
    public const ERROR_TOKEN_REFRESH_FAILED = 2;
    public const ERROR_CONNECTION_NOT_FOUND = 3;
    public const ERROR_CONNECTION_DISABLED = 4;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function requestFailed(string $reason, ?\Throwable $previous = null): self
    {
        $message = sprintf('RDC request failed: %s', $reason);
        if (is_null($previous) === false) {
            $message = sprintf('%s: %s', $message, $previous->getMessage());
        }

        return new self(
            $message,
            self::ERROR_REQUEST_FAILED,
            $previous
        );
    }

    public static function tokenRefreshFailed(?\Throwable $previous = null): self
    {
        return new self(
            'Failed to refresh RDC access token',
            self::ERROR_TOKEN_REFRESH_FAILED,
            $previous
        );
    }

    public static function connectionNotFound(string $connectionId, array $available): self
    {
        return new self(
            sprintf(
                'RDC connection "%s" not found. Available: %s',
                $connectionId,
                implode(', ', $available)
            ),
            self::ERROR_CONNECTION_NOT_FOUND
        );
    }

    public static function connectionDisabled(string $connectionId): self
    {
        return new self(
            sprintf('RDC connection "%s" is disabled', $connectionId),
            self::ERROR_CONNECTION_DISABLED
        );
    }
}
