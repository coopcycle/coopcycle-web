<?php

namespace AppBundle\Entity\Sylius;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

class ExportCommand
{
    protected int $id;

    #[Groups(["default_invoice_line_item"])]
    private \DateTime $createdAt;

    private \DateTime $updatedAt;

    private UserInterface $createdBy;

    #[Groups(["default_invoice_line_item"])]
    private string $requestId;

    private Collection $orders;

    public function __construct(
        UserInterface $createdBy,
        string $requestId
    )
    {
        $this->createdBy = $createdBy;
        $this->requestId = $requestId;

        $this->orders = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function getCreatedBy(): UserInterface
    {
        return $this->createdBy;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrders(array $orders): void
    {
        foreach ($orders as $order) {
            $this->orders->add(new OrderExport($order, $this));
        }
    }
}
