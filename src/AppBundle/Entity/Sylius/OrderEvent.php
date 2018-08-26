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

    public function __construct(
        OrderInterface $order,
        $type,
        array $data = [],
        array $metadata = [],
        \DateTime $createdAt = null)
    {
        if (null === $createdAt) {
            $createdAt = new \DateTime();
        }

        $this->order = $order;
        $this->type = $type;
        $this->createdAt = $createdAt;
        $this->data = $data;
        $this->metadata = $metadata;
    }

    public function getOrder(): OrderInterface
    {
        return $this->order;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}
