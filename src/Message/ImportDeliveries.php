<?php

namespace AppBundle\Message;

class ImportDeliveries
{
    private $filename;

    public function __construct(string $filename, protected array $options = [])
    {
        $this->filename = $filename;
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public function getOptions()
    {
        return $this->options;
    }
}
