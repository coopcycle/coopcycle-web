<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc\DTO;

class ServiceRequestContact
{
    public function __construct(
        public readonly string $name = '',
        public readonly string $phone = '',
        public readonly string $email = '',
    ) {}
}