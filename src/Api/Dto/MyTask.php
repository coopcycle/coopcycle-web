<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Address;
use AppBundle\Entity\Task;
use DateTime;
use Symfony\Component\Serializer\Annotation\Groups;

final class MyTask
{
    #[Groups(["task"])]
    public readonly int $id;

    #[Groups(["task"])]
    public readonly DateTime $createdAt;

    #[Groups(["task"])]
    public readonly DateTime $updatedAt;

    #[Groups(["task"])]
    public readonly string $type;

    #[Groups(["task"])]
    public readonly string $status;

    //TODO; make non-nullable
    #[Groups(["task"])]
    public readonly ?Address $address;

    #[Groups(["task"])]
    public readonly DateTime $doneAfter;

    #[Groups(["task"])]
    public readonly DateTime $doneBefore;

    /**
     * @var DateTime
     * @deprecated
     */
    #[Groups(["task"])]
    public readonly DateTime $after;

    /**
     * @var DateTime
     * @deprecated
     */
    #[Groups(["task"])]
    public readonly DateTime $before;

    #[Groups(["task"])]
    public readonly ?Task $previous;

    #[Groups(["task"])]
    public readonly ?Task $next;

    #[Groups(["task"])]
    public readonly ?string $comment;

    #[Groups(["task"])]
    public readonly bool $hasIncidents;

    #[Groups(["task"])]
    public readonly string $orgName;

    #[Groups(["task"])]
    public readonly Metadata $metadata;

    /**
     * @param int $id
     * @param DateTime $createdAt
     * @param DateTime $updatedAt
     * @param string $type
     * @param string $status
     * @param Address $address
     * @param DateTime $doneAfter
     * @param DateTime $doneBefore
     * @param Task|null $previous
     * @param Task|null $next
     * @param string|null $comment
     * @param bool $hasIncidents
     * @param string $orgName
     * @param Metadata $metadata
     */
    public function __construct(int $id, DateTime $createdAt, DateTime $updatedAt, string $type, string $status, ?Address $address, DateTime $doneAfter, DateTime $doneBefore, ?Task $previous, ?Task $next, ?string $comment, bool $hasIncidents, string $orgName, Metadata $metadata)
    {
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->type = $type;
        $this->status = $status;
        $this->address = $address;
        $this->doneAfter = $doneAfter;
        $this->doneBefore = $doneBefore;
        $this->after = $doneAfter;
        $this->before = $doneBefore;
        $this->previous = $previous;
        $this->next = $next;
        $this->comment = $comment;
        $this->hasIncidents = $hasIncidents;
        $this->orgName = $orgName;
        $this->metadata = $metadata;
    }
}

final class Metadata {
    #[Groups(["task"])]
    public readonly int $delivery_position;

    #[Groups(["task"])]
    public readonly ?string $order_number;

    #[Groups(["task"])]
    public readonly ?string $payment_method;

    #[Groups(["task"])]
    public readonly ?int $order_total;

    /**
     * @param int $delivery_position
     * @param string|null $order_number
     * @param string|null $payment_method
     * @param int|null $order_total
     */
    public function __construct(int $delivery_position, ?string $order_number, ?string $payment_method, ?int $order_total)
    {
        $this->delivery_position = $delivery_position;
        $this->order_number = $order_number;
        $this->payment_method = $payment_method;
        $this->order_total = $order_total;
    }
}
