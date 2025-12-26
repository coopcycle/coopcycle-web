<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class MyTaskMetadataDto
{
    #[Groups(["task"])]
    public readonly ?int $delivery_position;

    /**
     * @var string|null Store URI/'@id'
     */
    #[Groups(["task"])]
    public readonly ?string $store;

    #[Groups(["task"])]
    public readonly ?int $order_id;

    #[Groups(["task"])]
    public readonly ?string $order_number;

    #[Groups(["task"])]
    public readonly ?string $payment_method;

    #[Groups(["task"])]
    public readonly ?int $order_total;

    #[Groups(["task"])]
    public readonly ?bool $has_loopeat_returns;

    #[Groups(["task"])]
    public readonly ?bool $zero_waste;

    public function __construct(
        ?int $delivery_position,
        ?string $store,
        ?int $order_id,
        ?string $order_number,
        ?string $payment_method,
        ?int $order_total,
        ?bool $has_loopeat_returns,
        ?bool $zero_waste
    )
    {
        $this->delivery_position = $delivery_position;
        $this->store = $store;
        $this->order_id = $order_id;
        $this->order_number = $order_number;
        $this->payment_method = $payment_method;
        $this->order_total = $order_total;
        $this->has_loopeat_returns = $has_loopeat_returns;
        $this->zero_waste = $zero_waste;
    }
}
