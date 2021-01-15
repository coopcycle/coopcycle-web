<?php

namespace AppBundle\Action\Cart;

use Symfony\Component\HttpFoundation\Request;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

class UpdateItem
{
    private $orderProcessor;
    private $orderItemQuantityModifier;

    public function __construct(OrderItemQuantityModifierInterface $orderItemQuantityModifier, OrderProcessorInterface $orderProcessor)
    {
        $this->orderProcessor = $orderProcessor;
        $this->orderItemQuantityModifier = $orderItemQuantityModifier;
    }

    public function __invoke($data, $id, $itemId, Request $request)
    {
        $payload = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $payload = json_decode($content, true);
        }

        if (!isset($payload['quantity'])) {
            return $data;
        }

        $quantity = (int) $payload['quantity'];

        foreach ($data->getItems() as $item) {
            if ($item->getId() === (int) $itemId) {
                $this->orderItemQuantityModifier->modify($item, $quantity);
                break;
            }
        }

        $this->orderProcessor->process($data);

        return $data;
    }
}
