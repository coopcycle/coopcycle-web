<?php

namespace AppBundle\Message;

class Email
{
    private $message;
    private $to;

    public function __construct(\Swift_Message $message, $to)
    {
        $this->message = $message;
        $this->to = $to;
    }

    public function getMessage(): \Swift_Message
    {
        return $this->message;
    }

    public function getTo()
    {
        return $this->to;
    }
}
