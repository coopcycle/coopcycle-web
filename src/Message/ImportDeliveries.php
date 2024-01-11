<?php

namespace AppBundle\Message;

class ImportDeliveries
{
    private $filename;

    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    public function getFilename()
    {
        return $this->filename;
    }
}
