<?php

namespace AppBundle\Message;

class Sms
{
    private $text;
    private $to;

    public function __construct(string $text, string $to)
    {
        $this->text = $text;
        $this->to = $to;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getTo(): string
    {
        return $this->to;
    }
}
