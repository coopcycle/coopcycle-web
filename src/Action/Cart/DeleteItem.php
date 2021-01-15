<?php

namespace AppBundle\Action\Cart;

use Symfony\Component\HttpFoundation\Request;
use Sylius\Component\Order\Modifier\OrderModifierInterface;

class DeleteItem
{
    public function __construct(OrderModifierInterface $orderModifier)
    {
        $this->orderModifier = $orderModifier;
    }

    public function __invoke($data, $id, $itemId, Request $request)
    {
        foreach ($data->getItems() as $item) {
            if ($item->getId() === (int) $itemId) {
                $this->orderModifier->removeFromOrder($data, $item);
                break;
            }
        }

        return $data;
    }
}
