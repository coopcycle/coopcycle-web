<?php

namespace AppBundle\Entity\Zelty;

use AppBundle\Entity\LocalBusiness;

/**
 * One line of Zelty API traffic, written by the Monolog handler on the "zelty" channel.
 *
 * This entity is read-only from the application's point of view: rows are inserted by
 * AppBundle\Monolog\ZeltyApiLogHandler, outside of the entity manager.
 */
class ApiLog
{
    const DIRECTION_OUTGOING = 'outgoing';
    const DIRECTION_INCOMING = 'incoming';

    protected $id;

    protected ?LocalBusiness $restaurant = null;

    protected string $direction = self::DIRECTION_OUTGOING;

    protected ?string $method = null;

    protected ?string $endpoint = null;

    protected ?int $statusCode = null;

    protected ?string $requestBody = null;

    protected ?string $responseBody = null;

    protected ?int $durationMs = null;

    protected ?string $level = null;

    protected ?string $message = null;

    protected ?string $error = null;

    protected $createdAt;

    public function getId()
    {
        return $this->id;
    }

    public function getRestaurant(): ?LocalBusiness
    {
        return $this->restaurant;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getRequestBody(): ?string
    {
        return $this->requestBody;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function isSuccessful(): bool
    {
        return $this->error === null
            && $this->statusCode !== null
            && $this->statusCode >= 200
            && $this->statusCode < 400;
    }
}
