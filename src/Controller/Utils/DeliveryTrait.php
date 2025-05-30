<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Api\Dto\DeliveryFormDeliveryMapper;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\PricingRulesBasedPrice;
use AppBundle\Entity\Sylius\UseArbitraryPrice;
use AppBundle\Form\Order\ExistingOrderType;
use AppBundle\Pricing\PricingManager;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\OrderFactory;
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
        DeliveryFormDeliveryMapper $deliveryMapper,
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

        $routes = $request->attributes->get('routes');

        return $this->render('store/deliveries/form.html.twig', $this->auth([
            'layout' => $request->attributes->get('layout'),
            'store' => $delivery->getStore(),
            'order' => $order,
            'delivery' => $delivery,
            'deliveryData' => $deliveryData,
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
            'back_route' => $routes['back'],
            'show_left_menu' => true,
            'isDispatcher' => $this->isGranted('ROLE_DISPATCHER'),
            'debug_pricing' => $request->query->getBoolean('debug', false),
        ]));
    }

    public function deliveryAction(
        $id,
        Request $request,
        OrderFactory $orderFactory,
        EntityManagerInterface $entityManager,
        OrderManager $orderManager,
        PricingManager $pricingManager,
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
                    $order = $pricingManager->createOrder($delivery, [
                        'pricingStrategy' => new UseArbitraryPrice($arbitraryPrice),
                    ]);
                } else {
                    $orderFactory->updateDeliveryPrice($order, $delivery, $arbitraryPrice);
                }
            }

            $entityManager->persist($delivery);
            $entityManager->flush();

            if ($form->has('bookmark')) {
                $isBookmarked = true === $form->get('bookmark')->getData();

                $order = $delivery->getOrder();

                if (null !== $order) {
                    $orderManager->setBookmark($order, $isBookmarked);
                    $entityManager->flush();
                }
            }

            return $this->redirectToRoute($routes['success']);
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
