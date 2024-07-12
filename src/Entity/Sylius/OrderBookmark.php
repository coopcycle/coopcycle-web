<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Order\OrderInterface;
use DateTime;
use Symfony\Component\Security\Core\User\UserInterface;

class OrderBookmark
{
    private int|null $id = null;

    public function __construct(
        private readonly OrderInterface $order,
        private readonly UserInterface $owner,
        /**
         * owner and users in this role can access the bookmark
         */
        private readonly ?string $role,
        private readonly DateTime $createdAt = new DateTime())
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): OrderInterface
    {
        return $this->order;
    }

    public function getOwner(): UserInterface
    {
        return $this->owner;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
}
