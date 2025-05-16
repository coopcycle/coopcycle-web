<?php

namespace AppBundle\Api\Dto;

final class ResourceApplication
{
    public $resource;

    public function __construct(object $resource)
    {
        $this->resource = $resource;
    }
}
