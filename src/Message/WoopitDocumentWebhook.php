<?php

namespace AppBundle\Message;

class WoopitDocumentWebhook
{
    public function __construct(string $object, string $type)
    {
        $this->object = $object;
        $this->type = $type;
    }

    public function getObject(): string
    {
        return $this->object;
    }

    public function getType(): string
    {
        return $this->type;
    }

}
