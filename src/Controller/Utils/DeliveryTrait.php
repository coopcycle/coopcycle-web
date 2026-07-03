<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Api\Dto\DeliveryMapper;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Service\OrderManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

trait DeliveryTrait
{
    /**
     * @return array
     */
    abstract protected function getDeliveryRoutes();

    public function deliveryItemReactFormAction(
        $id,
        Request $request,
        EntityManagerInterface $entityManager,
        DeliveryMapper $deliveryMapper,
        OrderManager $orderManager,
    ) {
        $delivery = $entityManager
            ->getRepository(Delivery::class)
            ->find($id);

        $this->accessControl($delivery, 'view');

        $order = $delivery->getOrder();
        $price = $order?->getDeliveryPrice();

        $formData = $deliveryMapper->map(
            $delivery,
            $order,
            $price instanceof ArbitraryPrice ? $price : null,
            !is_null($order) && $orderManager->hasBookmark($order)
        );

        return $this->render('store/deliveries/form.html.twig', $this->auth([
            'layout' => $request->attributes->get('layout'),
            'store' => $delivery->getStore(),
            'order' => $order,
            'delivery' => $delivery,
            'formData' => $formData,
            'routes' => $request->attributes->get('routes'),
            'show_left_menu' => true,
            'isDispatcher' => $this->isGranted('ROLE_DISPATCHER'),
            'debug_pricing' => $request->query->getBoolean('debug', false),
        ]));
    }

}

