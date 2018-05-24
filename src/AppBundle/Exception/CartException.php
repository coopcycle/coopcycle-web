<?php

namespace AppBundle\Exception;

final class CartException extends \RuntimeException
{
    private $errors = [];

    public function __construct(array $errors)
    {
        $this->errors = $errors;

        parent::__construct('');
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
