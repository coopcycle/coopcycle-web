<?php

namespace AppBundle\Action\Cart;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Sylius\Component\Order\Modifier\OrderModifierInterface;

class DeleteItem
{
    public function __construct(
        private OrderModifierInterface $orderModifier,
        private EntityManagerInterface $entityManager
    )
    {}

    public function __invoke($data, $id, $itemId, Request $request)
    {
        foreach ($data->getItems() as $item) {
            if ($item->getId() === (int) $itemId) {
                $this->orderModifier->removeFromOrder($data, $item);
                break;
            }
        }

        // Make sure to flush changes as WriteListener is disabled
        $this->entityManager->flush();,

        return $data;
    }
}
