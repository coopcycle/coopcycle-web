<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\PriceInterface;
use AppBundle\Exception\Pricing\NoRuleMatchedException;
use AppBundle\Form\DeliveryType;
use AppBundle\Form\Order\OneOffOrderType;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

trait DeliveryTrait
{
    /**
     * @return array
     */
    abstract protected function getDeliveryRoutes();

    protected function createOrderForDelivery(OrderFactory $factory, Delivery $delivery, PriceInterface $price, ?CustomerInterface $customer = null, bool $attach = true): OrderInterface
    {
        return $factory->createForDelivery($delivery, $price, $customer, $attach);
    }

    protected function getDeliveryPrice(Delivery $delivery, PricingRuleSet $pricingRuleSet, DeliveryManager $deliveryManager)
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
        OrderNumberAssignerInterface $orderNumberAssigner,
        OrderManager $orderManager
    )
    {
        $delivery = $entityManager
            ->getRepository(Delivery::class)
            ->find($id);

        $this->accessControl($delivery, 'view');

        $routes = $request->attributes->get('routes');

        $form = $this->createForm(OneOffOrderType::class, $delivery, [
            'with_arbitrary_price' => null === $delivery->getOrder(),
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $delivery = $form->getData();

            $useArbitraryPrice = $this->isGranted('ROLE_ADMIN') &&
                $form->has('arbitraryPrice') && true === $form->get('arbitraryPrice')->getData();

            if ($useArbitraryPrice) {
                $this->createOrderForDeliveryWithArbitraryPrice($form, $orderFactory, $delivery,
                    $entityManager, $orderNumberAssigner);
            } else {
                $entityManager->persist($delivery);
                $entityManager->flush();
            }

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

    protected function createOrderForDeliveryWithArbitraryPrice(
        FormInterface $form,
        OrderFactory $orderFactory,
        Delivery $delivery,
        EntityManagerInterface $entityManager,
        OrderNumberAssignerInterface $orderNumberAssigner
    )
    {
        $variantPrice = $form->get('variantPrice')->getData();
        $variantName = $form->get('variantName')->getData();

        $order = $this->createOrderForDelivery($orderFactory, $delivery, new ArbitraryPrice($variantName, $variantPrice));

        $order->setState(OrderInterface::STATE_ACCEPTED);

        $entityManager->persist($order);
        $entityManager->flush();

        $orderNumberAssigner->assignNumber($order);

        $entityManager->flush();
    }
}
