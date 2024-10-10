<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class TaskMetadataDto
{
    #[Groups(["task"])]
    public readonly ?int $delivery_position;

    #[Groups(["task"])]
    public readonly ?string $order_number;

    #[Groups(["task"])]
    public readonly ?string $payment_method;

    #[Groups(["task"])]
    public readonly ?int $order_total;

    /**
     * @param int|null $deliveryPosition
     * @param string|null $orderNumber
     * @param string|null $paymentMethod
     * @param int|null $orderTotal
     */
    public function __construct(?int $deliveryPosition, ?string $orderNumber, ?string $paymentMethod, ?int $orderTotal)
    {
        $this->delivery_position = $deliveryPosition;
        $this->order_number = $orderNumber;
        $this->payment_method = $paymentMethod;
        $this->order_total = $orderTotal;
    }
}
