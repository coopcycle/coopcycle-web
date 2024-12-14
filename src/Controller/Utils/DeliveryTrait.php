<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\PricingRulesBasedPrice;
use AppBundle\Exception\Pricing\NoRuleMatchedException;
use AppBundle\Form\Order\ExistingOrderType;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\OrderFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

trait DeliveryTrait
{
    /**
     * @return array
     */
    abstract protected function getDeliveryRoutes();

    protected function getDeliveryPrice(Delivery $delivery, ?PricingRuleSet $pricingRuleSet, DeliveryManager $deliveryManager)
    {
        $price = $deliveryManager->getPrice($delivery, $pricingRuleSet);

        if (null === $price) {
            throw new NoRuleMatchedException();
        }

        return (int) ($price);
    }

    public function deliveryAction($id,
        Request $request,
        OrderFactory $orderFactory,
        EntityManagerInterface $entityManager,
        OrderManager $orderManager
    )
    {
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
                $orderFactory->updateDeliveryPrice($order, $delivery, $arbitraryPrice);
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

        return $this->render('delivery/item.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'delivery' => $delivery,
            'form' => $form->createView(),
            'debug_pricing' => $request->query->getBoolean('debug', false),
            'back_route' => $routes['back'],
        ]);
    }

}
