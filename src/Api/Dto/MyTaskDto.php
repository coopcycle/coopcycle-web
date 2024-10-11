<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Address;
use DateTime;
use Symfony\Component\Serializer\Annotation\Groups;

final class MyTaskDto
{
    #[Groups(["task"])]
    public readonly int $id;

    #[Groups(["task"])]
    public readonly DateTime $createdAt;

    #[Groups(["task"])]
    public readonly DateTime $updatedAt;

    #[Groups(["task"])]
    public readonly string $orgName;

    #[Groups(["task"])]
    public readonly string $type;

    #[Groups(["task"])]
    public readonly string $status;

    #[Groups(["task"])]
    public readonly Address $address;

    #[Groups(["task"])]
    public readonly DateTime $after;

    #[Groups(["task"])]
    public readonly DateTime $before;

    #[Groups(["task"])]
    public readonly ?int $previous;

    #[Groups(["task"])]
    public readonly ?int $next;

    #[Groups(["task"])]
    public readonly array $tags;

    #[Groups(["task"])]
    public readonly bool $doorstep;

    #[Groups(["task"])]
    public readonly ?string $comment;

    #[Groups(["task"])]
    public readonly array $packages;

    #[Groups(["task"])]
    public readonly ?int $weight;

    #[Groups(["task"])]
    public readonly bool $hasIncidents;

    #[Groups(["task"])]
    public readonly ?int $delivery_position;

    #[Groups(["task"])]
    public readonly ?string $order_number;

    #[Groups(["task"])]
    public readonly ?string $payment_method;

    #[Groups(["task"])]
    public readonly ?int $order_total;

    public function __construct(
        int $id,
        DateTime $createdAt,
        DateTime $updatedAt,
        string $type,
        string $status,
        Address $address,
        DateTime $after,
        DateTime $before,
        ?int $previous,
        ?int $next,
        array $tags,
        bool $doorstep,
        ?string $comment,
        array $packages,
        ?int $weight,
        bool $hasIncidents,
        string $orgName,
        ?int $deliveryPosition,
        ?string $orderNumber,
        ?string $paymentMethod,
        ?int $orderTotal
    )
    {
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->type = $type;
        $this->status = $status;
        $this->address = $address;
        $this->after = $after;
        $this->before = $before;
        $this->previous = $previous;
        $this->next = $next;
        $this->tags = $tags;
        $this->doorstep = $doorstep;
        $this->comment = $comment;
        $this->packages = $packages;
        $this->weight = $weight;
        $this->hasIncidents = $hasIncidents;
        $this->orgName = $orgName;
        $this->delivery_position = $deliveryPosition;
        $this->order_number = $orderNumber;
        $this->payment_method = $paymentMethod;
        $this->order_total = $orderTotal;
    }
}
