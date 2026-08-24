<?php

namespace AppBundle\Monolog;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Persists the "zelty" channel to the zelty_api_log table, so API traffic can be
 * reviewed from the admin UI.
 *
 * Writes go through a dedicated connection: log lines must survive a rollback of
 * whatever transaction the application is in (the catalog import runs in one).
 */
class ZeltyApiLogHandler extends AbstractProcessingHandler
{
    const MAX_BODY_LENGTH = 32768;

    private ?Connection $logConnection = null;

    public function __construct(
        private readonly Connection $connection,
        int|string|Level $level = Level::Info,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $context = $record->context;

        try {
            $this->getLogConnection()->insert('zelty_api_log', [
                'restaurant_id' => $context['restaurant_id'] ?? null,
                'direction'     => $context['direction'] ?? \AppBundle\Entity\Zelty\ApiLog::DIRECTION_OUTGOING,
                'method'        => $context['method'] ?? null,
                'endpoint'      => $context['endpoint'] ?? null,
                'status_code'   => $context['status_code'] ?? null,
                'request_body'  => $this->prepareBody($context['request_body'] ?? null),
                'response_body' => $this->prepareBody($context['response_body'] ?? null),
                'duration_ms'   => $context['duration_ms'] ?? null,
                'level'         => $record->level->getName(),
                'message'       => $record->message,
                'error'         => $context['error'] ?? null,
                'created_at'    => $record->datetime->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Logging must never break the operation being logged.
        }
    }

    /**
     * A separate connection, cloned from the application's parameters, so an
     * application rollback does not take the log lines with it.
     */
    private function getLogConnection(): Connection
    {
        if ($this->logConnection === null) {
            $this->logConnection = DriverManager::getConnection(
                $this->connection->getParams()
            );
        }

        return $this->logConnection;
    }

    protected function prepareBody(mixed $body): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }

        if (!is_string($body)) {
            $body = json_encode($body);
        }

        $body = $this->redact($body);

        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            // Catalogs run into megabytes; keep the head, which is where the shape is.
            return mb_substr($body, 0, self::MAX_BODY_LENGTH) . '… [truncated]';
        }

        return $body;
    }

    /**
     * The webhook endpoints hand back the shared secret, which has no business
     * being readable in a log table.
     */
    private function redact(string $body): string
    {
        return preg_replace(
            '/("(?:secret_key|secretKey)"\s*:\s*)"[^"]*"/',
            '$1"[redacted]"',
            $body
        ) ?? $body;
    }
}
