<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Address;
use DateTime;
use Symfony\Component\Serializer\Annotation\Groups;

final class TaskDto
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
    public readonly Address $address;

    /**
     * @var DateTime
     * @deprecated
     */
    #[Groups(["task"])]
    public readonly DateTime $doneAfter;

    /**
     * @var DateTime
     * @deprecated
     */
    #[Groups(["task"])]
    public readonly DateTime $doneBefore;

    #[Groups(["task"])]
    public readonly DateTime $after;

    #[Groups(["task"])]
    public readonly DateTime $before;

    #[Groups(["task"])]
    public readonly ?int $previous;

    #[Groups(["task"])]
    public readonly ?int $next;

    #[Groups(["task"])]
    public readonly ?string $comment;

    #[Groups(["task"])]
    public readonly array $packages;

    #[Groups(["task"])]
    public readonly bool $hasIncidents;

    #[Groups(["task"])]
    public readonly string $orgName;

    #[Groups(["task"])]
    public readonly TaskMetadataDto $metadata;

    /**
     * @param int $id
     * @param DateTime $createdAt
     * @param DateTime $updatedAt
     * @param string $type
     * @param string $status
     * @param Address $address
     * @param DateTime $after
     * @param DateTime $before
     * @param int|null $previous
     * @param int|null $next
     * @param string|null $comment
     * @param array $packages
     * @param bool $hasIncidents
     * @param string $orgName
     * @param TaskMetadataDto $metadata
     */
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
        ?string $comment,
        array $packages,
        bool $hasIncidents,
        string $orgName,
        TaskMetadataDto $metadata)
    {
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->type = $type;
        $this->status = $status;
        $this->address = $address;
        $this->doneAfter = $after;
        $this->doneBefore = $before;
        $this->after = $after;
        $this->before = $before;
        $this->previous = $previous;
        $this->next = $next;
        $this->comment = $comment;
        $this->packages = $packages;
        $this->hasIncidents = $hasIncidents;
        $this->orgName = $orgName;
        $this->metadata = $metadata;
    }
}
