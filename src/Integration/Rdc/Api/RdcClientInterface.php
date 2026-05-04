<?php

namespace AppBundle\Integration\Rdc\Api;

use Symfony\Contracts\HttpClient\ResponseInterface;

interface RdcClientInterface
{
    public function get(string $path, array $query = []): ResponseInterface;

    public function post(string $path, array $data, array $headers = []): ResponseInterface;

    public function patch(string $path, array $patchOperations, array $headers = []): ResponseInterface;

    public function createEvent(string $path, array $eventData): ResponseInterface;

    public function getTraceparent(): string;

    public function getToken(): string;
}
