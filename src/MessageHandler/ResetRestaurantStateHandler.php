<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Message\ResetRestaurantState;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ResetRestaurantStateHandler
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(ResetRestaurantState $message)
    {
        $restaurant = $this->entityManager->getRepository(LocalBusiness::class)->find($message->getId());
        $restaurant->setState(LocalBusiness::STATE_NORMAL);

        $this->entityManager->flush();
    }
}

