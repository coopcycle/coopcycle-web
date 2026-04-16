<?php

namespace AppBundle\Integration\Rdc\Api;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * RDC API client interface for HTTP operations.
 */
interface RdcClientInterface
{
    /**
     * Perform a GET request.
     */
    public function get(string $path, array $query = []): ResponseInterface;

    /**
     * Perform a POST request with JSON body.
     */
    public function post(string $path, array $data, array $query = [], array $headers = []): ResponseInterface;

    /**
     * Perform a PATCH request with JSON body.
     */
    public function patch(string $path, array $data, array $headers = []): ResponseInterface;

    /**
     * Perform a POST request to a remote URL with optional token override.
     */
    public function postRemote(string $url, array $data, ?string $tokenOverride = null): ResponseInterface;

    public function getBaseUrl(): string;
}
