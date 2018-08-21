<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Order\OrderInterface;

class OrderEvent
{
    private $id;
    private $order;
    private $type;
    private $data = [];
    private $metadata = [];
    private $createdAt;

    public function getOrder(): OrderInterface
    {
        return $this->order;
    }

    public function setOrder(OrderInterface $order)
    {
        $this->order = $order;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata)
    {
        $this->metadata = $metadata;

        return $this;
    }
}
