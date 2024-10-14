<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class MyTaskMetadataDto
{
    #[Groups(["task"])]
    public readonly ?int $delivery_position;

    #[Groups(["task"])]
    public readonly ?string $order_number;

    #[Groups(["task"])]
    public readonly ?string $payment_method;

    #[Groups(["task"])]
    public readonly ?int $order_total;

    #[Groups(["task"])]
    public readonly bool $has_loopeat_returns;

    public function __construct(
        ?int $delivery_position,
        ?string $order_number,
        ?string $payment_method,
        ?int $order_total,
        bool $has_loopeat_returns
    )
    {
        $this->delivery_position = $delivery_position;
        $this->order_number = $order_number;
        $this->payment_method = $payment_method;
        $this->order_total = $order_total;
        $this->has_loopeat_returns = $has_loopeat_returns;
    }
}
