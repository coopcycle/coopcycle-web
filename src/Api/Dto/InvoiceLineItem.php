<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class InvoiceLineItem
{
    #[Groups(["order"])]
    public readonly int $orderId;

    #[Groups(["order"])]
    public readonly string $orderNumber;

    #[Groups(["order"])]
    public readonly \DateTime $date;

    #[Groups(["order"])]
    public readonly string $description;

    #[Groups(["order"])]
    public readonly float $subTotal;

    #[Groups(["order"])]
    public readonly float $tax;

    #[Groups(["order"])]
    public readonly float $total;

    public function __construct(
        int $orderId,
        string $orderNumber,
        \DateTime $date,
        string $description,
        float $subTotal,
        float $tax,
        float $total
    )
    {
        $this->orderId = $orderId;
        $this->orderNumber = $orderNumber;
        $this->date = $date;
        $this->description = $description;
        $this->subTotal = $subTotal;
        $this->tax = $tax;
        $this->total = $total;
    }
}
