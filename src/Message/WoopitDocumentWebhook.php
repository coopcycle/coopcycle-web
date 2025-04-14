<?php

namespace AppBundle\Message;

class WoopitDocumentWebhook
{
    public function __construct(private string $object, private string $type)
    {}

    public function getObject(): string
    {
        return $this->object;
    }

    public function getType(): string
    {
        return $this->type;
    }

}
