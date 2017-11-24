<?php

namespace AppBundle\Action\Order;


use AppBundle\Action\ActionTrait;
use AppBundle\Entity\Order;
use AppBundle\Entity\OrderItemModifier;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class Create
{
    use ActionTrait;

    /**
     * @Route(
     *     name="order_create",
     *     path="/orders",
     *     defaults={
     *         "_api_resource_class"=Order::class,
     *         "_api_collection_operation_name"="my_orders"
     *     }
     * )
     * @Method("POST")
     */
    public function __invoke(Order $data) {

        // Set delivery price in backend
        $delivery = $data->getDelivery();
        $deliveryPrice = $data->getRestaurant()->getFlatDeliveryPrice();
        $delivery->setPrice($deliveryPrice);

        // Order MUST have status = CREATED
        if ($data->getStatus() !== Order::STATUS_CREATED) {
            throw new BadRequestHttpException(sprintf('Order must be created with status #%s', Order::STATUS_CREATED));
        }

        // HACK: set manually order_item_id on order_item_modifier - hopefully this get fixed : https://github.com/api-platform/api-platform/issues/430
        $manager = $this->doctrine->getManagerForClass(OrderItemModifier::class);
        foreach ($data->getOrderedItem() as $value) {
            foreach ($value->getModifiers() as $modifier) {
                $modifier->setOrderItem($value);
                $manager->persist($modifier);
            }
        }

        $manager->flush();

        return $data;
    }

}