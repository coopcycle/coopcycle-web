<?php

namespace AppBundle\Message;

class ImportTasks
{
    private $token;
    private $filename;
    private $date;
    private $queueId;

    public function __construct($token, $filename, \DateTime $date,
        $queueId = null)
    {
        $this->token = $token;
        $this->filename = $filename;
        $this->date = $date;
        $this->queueId = $queueId;
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

    public function getQueueId()
    {
        return $this->queueId;
    }
}
