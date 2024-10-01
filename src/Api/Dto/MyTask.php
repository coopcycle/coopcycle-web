<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class MyTask
{
    #[Groups(["task"])]
    public readonly int $id;

    #[Groups(["task"])]
    public readonly string $type;

    #[Groups(["task"])]
    public readonly string $status;

    //todo; move into metadata
    #[Groups(["task"])]
    public readonly ?int $orderId;

    /**
     * @param int $id
     * @param string $type
     * @param string $status
     * @param int|null $orderId
     */
    public function __construct(int $id, string $type, string $status, ?int $orderId)
    {
        $this->id = $id;
        $this->type = $type;
        $this->status = $status;
        $this->orderId = $orderId;
    }
}
