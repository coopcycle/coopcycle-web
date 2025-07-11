<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Api\Dto\DeliveryMapper;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\PricingRulesBasedPrice;
use AppBundle\Entity\Sylius\UseArbitraryPrice;
use AppBundle\Form\Order\ExistingOrderType;
use AppBundle\Pricing\PriceCalculationVisitor;
use AppBundle\Pricing\PricingManager;
use AppBundle\Service\DeliveryOrderManager;
use AppBundle\Service\OrderManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
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

        $deliveryData = $deliveryMapper->map(
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
            'deliveryData' => $deliveryData,
            'routes' => $request->attributes->get('routes'),
            'show_left_menu' => true,
            'isDispatcher' => $this->isGranted('ROLE_DISPATCHER'),
            'debug_pricing' => $request->query->getBoolean('debug', false),
        ]));
    }

    public function deliveryAction(
        $id,
        Request $request,
        EntityManagerInterface $entityManager,
        OrderManager $orderManager,
        PricingManager $pricingManager,
        DeliveryOrderManager $deliveryOrderManager,
    ) {
        $delivery = $entityManager
            ->getRepository(Delivery::class)
            ->find($id);

        $this->accessControl($delivery, 'view');

        $routes = $request->attributes->get('routes');

        $order = $delivery->getOrder();
        $price = $order?->getDeliveryPrice();

        $form = $this->createForm(ExistingOrderType::class, $delivery, [
            'pricing_rules_based_price' => $price instanceof PricingRulesBasedPrice ? $price : null,
            'arbitrary_price' => $price instanceof ArbitraryPrice ? $price : null
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $delivery = $form->getData();

            $useArbitraryPrice = $this->isGranted('ROLE_ADMIN') &&
                $form->has('arbitraryPrice') && true === $form->get('arbitraryPrice')->getData();

            if ($useArbitraryPrice) {
                $arbitraryPrice = $this->getArbitraryPrice($form);
                if (null === $order) {
                    // Should not happen normally, but just in case
                    // there is still some delivery created without an order
                    $order = $deliveryOrderManager->createOrder($delivery, [
                        'pricingStrategy' => new UseArbitraryPrice($arbitraryPrice),
                    ]);
                } else {
                    $pricingManager->processDeliveryOrder($order, [$pricingManager->getCustomProductVariant($delivery, $arbitraryPrice)]);
                }
            }

            $entityManager->persist($delivery);
            $entityManager->flush();

            if ($form->has('bookmark')) {
                $isBookmarked = true === $form->get('bookmark')->getData();

                if (null !== $order) {
                    $orderManager->setBookmark($order, $isBookmarked);
                    $entityManager->flush();
                }
            }

            if (!is_null($order)) {
                return $this->redirectToRoute('admin_order', [ 'id' => $order->getId() ]);
            } else {
                return $this->redirectToRoute('admin_deliveries');
            }
        }

        return $this->render('delivery/item_legacy.html.twig', $this->auth([
            'delivery' => $delivery,
            'layout' => $request->attributes->get('layout'),
            'form' => $form->createView(),
            'debug_pricing' => $request->query->getBoolean('debug', false),
            'back_route' => $routes['back'],
        ]));
    }

    private function getArbitraryPrice(FormInterface $form): ?ArbitraryPrice
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return null;
        }

        if (!$form->has('arbitraryPrice')) {
            return null;
        }

        if (true !== $form->get('arbitraryPrice')->getData()) {
            return null;
        }

        $variantPrice = $form->get('variantPrice')->getData();
        $variantName = $form->get('variantName')->getData();

        return new ArbitraryPrice($variantName, $variantPrice);
    }
}
