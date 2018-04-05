<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\StripePayment;
use AppBundle\Form\DeliveryType;
use AppBundle\Sylius\Order\AdjustmentInterface;
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
     * @param UserInterface $user
     *
     * @return Sylius\Component\Order\Model\OrderInterface
     */
    protected function createOrderForDelivery(Delivery $delivery, int $price, UserInterface $user)
    {
        $orderFactory = $this->container->get('sylius.factory.order');
        $orderItemFactory = $this->container->get('sylius.factory.order_item');
        $productVariantFactory = $this->get('sylius.factory.product_variant');

        $order = $orderFactory->createNew();
        $order->setCustomer($user);

        $variant = $productVariantFactory->createForDelivery($delivery, $price);

        $orderItem = $orderItemFactory->createNew();
        $orderItem->setVariant($variant);
        $orderItem->setUnitPrice($variant->getPrice());
        $this->container->get('sylius.order_item_quantity_modifier')->modify($orderItem, 1);

        $order->addItem($orderItem);
        $this->get('sylius.order_modifier')->addToOrder($order, $orderItem);

        return $order;
    }

    protected function createDeliveryForm(Delivery $delivery, array $options = [])
    {
        if ($delivery->getId() === null) {

            $pickupDoneBefore = new \DateTime('+1 day');
            while (($pickupDoneBefore->format('i') % 15) !== 0) {
                $pickupDoneBefore->modify('+1 minute');
            }

            $dropoffDoneBefore = clone $pickupDoneBefore;
            $dropoffDoneBefore->modify('+1 hour');

            $delivery->getPickup()->setDoneBefore($pickupDoneBefore);
            $delivery->getDropoff()->setDoneBefore($dropoffDoneBefore);
        }

        return $this->createForm(DeliveryType::class, $delivery, $options);
    }

    protected function getDeliveryPrice(Delivery $delivery, PricingRuleSet $pricingRuleSet)
    {
        $price = $this->get('coopcycle.delivery.manager')
            ->getPrice($delivery, $pricingRuleSet);

        if (null === $price) {
            throw new \Exception('Price could not be calculated');
        }

        return (int) ($price * 100);
    }

    protected function handleDeliveryForm(FormInterface $form, PricingRuleSet $pricingRuleSet = null)
    {
        $delivery = $form->getData();

        if (null !== $delivery->getId()) {
            return;
        }

        if (!$pricingRuleSet) {
            $pricingRuleSet = $form->get('pricingRuleSet')->getData();
        }

        try {
            return $this->getDeliveryPrice($delivery, $pricingRuleSet);
        } catch (\Exception $e) {
            $message = $this->get('translator')->trans('delivery.price.error.priceCalculation', [], 'validators');
            $form->addError(new FormError($message));
        }
    }

    private function renderDeliveryForm(Delivery $delivery, Request $request, array $options = [])
    {
        $routes = $request->attributes->get('routes');

        $isNew = $delivery->getId() === null;
        $order = null;

        if (!$isNew && $this->getUser()->hasRole('ROLE_ADMIN')) {
            $order = $delivery->getSyliusOrder();
        }

        $form = $this->createDeliveryForm($delivery, $options);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {

            $this->handleDeliveryForm($form);

            if ($form->isValid()) {

                $delivery = $form->getData();

                if ($isNew) {
                    $this->getDoctrine()->getManagerForClass(Delivery::class)->persist($delivery);
                }

                $this->getDoctrine()->getManagerForClass(Delivery::class)->flush();

                return $this->redirectToRoute($routes['success']);
            }
        }

        return $this->render('AppBundle:Delivery:form.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'delivery' => $delivery,
            'form' => $form->createView(),
            'calculate_price_route' => $routes['calculate_price'],
            'order' => $order
        ]);
    }

    public function calculateDeliveryPriceAction(Request $request)
    {
        $deliveryManager = $this->get('coopcycle.delivery.manager');

        if (!$request->query->has('pricing_rule_set')) {
            throw new BadRequestHttpException('No pricing provided');
        }

        if (empty($request->query->get('pricing_rule_set'))) {
            throw new BadRequestHttpException('No pricing provided');
        }

        $delivery = new Delivery();
        $delivery->setDistance($request->query->get('distance'));
        $delivery->setVehicle($request->query->get('vehicle', null));
        $delivery->setWeight($request->query->get('weight', null));

        $deliveryAddressCoords = $request->query->get('delivery_address');
        [ $latitude, $longitude ] = explode(',', $deliveryAddressCoords);

        $pricingRuleSet = $this->getDoctrine()
            ->getRepository(PricingRuleSet::class)->find($request->query->get('pricing_rule_set'));

        $deliveryAddress = new Address();
        $deliveryAddress->setGeo(new GeoCoordinates($latitude, $longitude));

        $delivery->setDeliveryAddress($deliveryAddress);

        return new JsonResponse($deliveryManager->getPrice($delivery, $pricingRuleSet));
    }
}
