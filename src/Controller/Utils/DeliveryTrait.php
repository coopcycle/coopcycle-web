<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Api\Dto\DeliveryMapper;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Shopify\ShopifyOrder;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Service\OrderManager;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

trait DeliveryTrait
{
    /**
     * @return array
     */
    abstract protected function getDeliveryRoutes();

    /**
     * Parses the "start_at" & "end_at" query parameters used to filter
     * delivery lists (both in the admin & in the store dashboard).
     *
     * @return array{0: \DateTime, 1: \DateTime}|null
     */
    protected function getDeliveryDateRange(Request $request): ?array
    {
        if (!$request->query->get('start_at') || !$request->query->get('end_at')) {

            return null;
        }

        return [
            Carbon::parse($request->query->get('start_at'))->setTime(0, 0, 0)->toDateTime(),
            Carbon::parse($request->query->get('end_at'))->setTime(23, 59, 59)->toDateTime(),
        ];
    }

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
            'shopify_order' => $this->getShopifyOrderData($entityManager, $delivery),
            'formData' => $formData,
            'routes' => $request->attributes->get('routes'),
            'show_left_menu' => true,
            'isDispatcher' => $this->isGranted('ROLE_DISPATCHER'),
            'debug_pricing' => $request->query->getBoolean('debug', false),
        ]));
    }

    /**
     * Deliveries created from a Shopify order look identical to any other in the
     * dispatch view, which makes them hard to reconcile with the merchant's shop.
     * Surface the link when there is one.
     *
     * @return array{name: string, url: string}|null
     */
    private function getShopifyOrderData(EntityManagerInterface $entityManager, Delivery $delivery): ?array
    {
        if (null === $delivery->getId()) {
            return null;
        }

        $shopifyOrder = $entityManager->getRepository(ShopifyOrder::class)
            ->findOneBy(['delivery' => $delivery]);

        if (null === $shopifyOrder || null === $shopifyOrder->getShop()) {
            return null;
        }

        return [
            'name' => $shopifyOrder->getShopifyOrderName(),
            // The shop admin uses the numeric order id, not the display name.
            'url'  => sprintf(
                'https://%s/admin/orders/%s',
                $shopifyOrder->getShop()->getShopDomain(),
                $shopifyOrder->getShopifyOrderId()
            ),
        ];
    }
}

