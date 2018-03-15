<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryOrder;
use AppBundle\Entity\DeliveryOrderItem;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Store;
use AppBundle\Form\DeliveryType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait DeliveryTrait
{
    /**
     * @return array
     */
    abstract protected function getDeliveryRoutes();

    private function getDeliveryOrder(Delivery $delivery)
    {
        $deliveryOrderItem = $this->getDoctrine()
                ->getRepository(DeliveryOrderItem::class)
                ->findOneByDelivery($delivery);

        if ($deliveryOrderItem) {
            return $deliveryOrderItem->getOrderItem()->getOrder();
        }
    }

    private function renderDeliveryForm(Delivery $delivery, Request $request, Store $store = null, array $options = [])
    {
        $isNew = $delivery->getId() === null;
        $routes = $request->attributes->get('routes');
        $deliveryOrder = null;

        if ($isNew) {
            if ($store) {
                $delivery->setDate($store->getNextOpeningDate());
            } else {
                $date = new \DateTime('+1 hour');
                while (($date->format('i') % 15) !== 0) {
                    $date->modify('+1 minute');
                }
                $delivery->setDate($date);
            }
        } else {
            if ($this->getUser()->hasRole('ROLE_ADMIN')) {
                $deliveryOrder = $this->getDeliveryOrder($delivery);
            }
        }

        $defaultOptions = [
            'free_pricing' => $store === null,
            'pricing_rule_set' => $store !== null ? $store->getPricingRuleSet() : null
        ];
        $options = array_merge($defaultOptions, $options);

        $form = $this->createForm(DeliveryType::class, $delivery, $options);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {

            $delivery = $form->getData();
            $user = $this->getUser();

            $em = $this->getDoctrine()->getManagerForClass(Delivery::class);

            if (!$store && !$user->hasRole('ROLE_ADMIN')) {
                $form->addError(new FormError('Unable to create a delivery not linked to a store for a non-admin user'));
            }

            if ($form->isValid()) {

                $deliveryManager = $this->get('coopcycle.delivery.manager');

                if ($store) {
                    // if the user is not admin, he cannot override the set pricing
                    if (!$user->hasRole('ROLE_ADMIN')) {
                        $price = $deliveryManager->getPrice($delivery, $store->getPricingRuleSet());
                        $delivery->setPrice($price);
                    }
                    $delivery->setStore($store);
                }

                $deliveryManager->applyTaxes($delivery);

                if ($isNew) {
                    $em->persist($delivery);
                }

                $em->flush();

                return $this->redirectToRoute($routes['success']);
            }
        }

        return $this->render("AppBundle:Delivery:form.html.twig", [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'delivery' => $delivery,
            'form' => $form->createView(),
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
            'calculate_price_route' => $routes['calculate_price'],
            'delivery_order' => $deliveryOrder
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
