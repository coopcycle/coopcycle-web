<?php

namespace AppBundle\Message;

class Event
{
    private $name;
    private $data;

    public function __construct($name, $data = [])
    {
        $this->name = $name;
        $this->data = $data;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getData()
    {
        return $this->data;
    }
}
