<?php

namespace AppBundle\Action\Order;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Service\OrderManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class RemoveBookmark
{
    public function __construct(
        private OrderManager $orderManager,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(Order $data): Response
    {
        $this->orderManager->setBookmark($data, false);

        // As we configure write = false at operation level,
        // we have to flush changes here
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
