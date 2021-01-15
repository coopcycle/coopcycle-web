<?php

namespace AppBundle\Message;

use Symfony\Component\Mime\Email as MimeEmail;

class Email
{
    private $message;
    private $to;

    public function __construct(MimeEmail $message, $to)
    {
        $this->message = $message;
        $this->to = $to;
    }

    public function getMessage(): MimeEmail
    {
        return $this->message;
    }

    public function getTo()
    {
        return $this->to;
    }
}
