<?php

namespace AppBundle\Integration\Rdc\Api;

interface TokenManagerInterface
{
    public function getValidToken(): string;

    public function refreshToken(): string;

    public function revokeToken(): void;
}