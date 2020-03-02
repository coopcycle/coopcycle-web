<?php

namespace AppBundle\Message;

class ImportTasks
{
    private $token;
    private $filename;
    private $date;

    public function __construct($token, $filename, \DateTime $date)
    {
        $this->token = $token;
        $this->filename = $filename;
        $this->date = $date;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public function getDate()
    {
        return $this->date;
    }
}
