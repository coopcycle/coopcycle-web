<?php

namespace AppBundle\Controller\Utils;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Exception\Pricing\NoRuleMatchedException;
use AppBundle\Form\DeliveryType;
use AppBundle\Service\DeliveryManager;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

trait DeliveryTrait
{
    /**
     * @return array
     */
    abstract protected function getDeliveryRoutes();

    /**
     * @param Delivery $delivery
     * @param int $price
     * @param CustomerInterface $customer
     *
     * @return OrderInterface
     */
    protected function createOrderForDelivery(Delivery $delivery, int $price, ?CustomerInterface $customer = null)
    {
        $orderFactory = $this->container->get('sylius.factory.order');
        $orderItemFactory = $this->container->get('sylius.factory.order_item');
        $productVariantFactory = $this->get('sylius.factory.product_variant');

        $order = $orderFactory->createNew();
        $order->setDelivery($delivery);
        $order->setShippingAddress($delivery->getDropoff()->getAddress());

        $shippingTimeRange = new TsRange();

        if (null === $delivery->getDropoff()->getAfter()) {
            $dropoffAfter = clone $delivery->getDropoff()->getBefore();
            $dropoffAfter->modify('-15 minutes');
            $delivery->getDropoff()->setAfter($dropoffAfter);
        }
        $shippingTimeRange->setLower($delivery->getDropoff()->getAfter());
        $shippingTimeRange->setUpper($delivery->getDropoff()->getBefore());

        $order->setShippingTimeRange($shippingTimeRange);

        if (null !== $customer) {
            $order->setCustomer($customer);
        }

        $variant = $productVariantFactory->createForDelivery($delivery, $price);

        $orderItem = $orderItemFactory->createNew();
        $orderItem->setVariant($variant);
        $orderItem->setUnitPrice($variant->getPrice());
        $this->container->get('sylius.order_item_quantity_modifier')->modify($orderItem, 1);

        $this->get('sylius.order_modifier')->addToOrder($order, $orderItem);

        return $order;
    }

    protected function createDeliveryForm(Delivery $delivery, array $options = [])
    {
        return $this->createForm(DeliveryType::class, $delivery, $options);
    }

    protected function getDeliveryPrice(Delivery $delivery, PricingRuleSet $pricingRuleSet, DeliveryManager $deliveryManager)
    {
        $price = $deliveryManager->getPrice($delivery, $pricingRuleSet);

        if (null === $price) {
            throw new NoRuleMatchedException();
        }

        return (int) ($price);
    }

    private function renderDeliveryForm(Delivery $delivery, Request $request, array $options = [])
    {
        $routes = $request->attributes->get('routes');

        $form = $this->createDeliveryForm($delivery, $options);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $delivery = $form->getData();

            if ($delivery->getId() === null) {
                $this->getDoctrine()->getManagerForClass(Delivery::class)->persist($delivery);
            }

            $this->getDoctrine()->getManagerForClass(Delivery::class)->flush();

            return $this->redirectToRoute($routes['success']);
        }

        return $this->render('delivery/form.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'delivery' => $delivery,
            'form' => $form->createView(),
            'debug_pricing' => $request->query->getBoolean('debug', false),
        ]);
    }

    public function deliveryAction($id, Request $request)
    {
        $delivery = $this->getDoctrine()
            ->getRepository(Delivery::class)
            ->find($id);

        $this->accessControl($delivery);

        return $this->renderDeliveryForm($delivery, $request);
    }
}
