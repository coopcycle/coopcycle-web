<?php

namespace AppBundle\Controller\Utils;

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

    public function deliveryActionBeta(
        $id,
        Request $request,
        EntityManagerInterface $entityManager,
    ) {
        $delivery = $entityManager
            ->getRepository(Delivery::class)
            ->find($id);

        $this->accessControl($delivery, 'view');

        $routes = $request->attributes->get('routes');

        return $this->render('store/deliveries/beta_new.html.twig', $this->auth([
            'delivery' => $delivery,
            'order' => $delivery->getOrder(),
            'store' => $delivery->getStore(),
            'layout' => $request->attributes->get('layout'),
            'debug_pricing' => $request->query->getBoolean('debug', false),
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
            'back_route' => $routes['back'],
            'show_left_menu' => true,
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

        return $this->render('delivery/item.html.twig', $this->auth([
            'delivery' => $delivery,
            'layout' => $request->attributes->get('layout'),
            'delivery' => $delivery,
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
