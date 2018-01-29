<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
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

    private function renderDeliveryForm(Delivery $delivery, Request $request, Store $store = null, array $options = [])
    {
        if ($store) {
            $delivery->setDate($store->getNextOpeningDate());
        } else {
            $date = new \DateTime('+1 hour');
            while (($date->format('i') % 15) !== 0) {
                $date->modify('+1 minute');
            }
            $delivery->setDate($date);
        }

        $translator = $this->get('translator');

        $options = array_merge([
            'free_pricing' => $store === null,
            'pricing_rule_set' => $store !== null ? $store->getPricingRuleSet() : null,
            'vehicle_choices' => [
                $translator->trans('form.delivery.vehicle.VEHICLE_BIKE') => Delivery::VEHICLE_BIKE,
                $translator->trans('form.delivery.vehicle.VEHICLE_CARGO_BIKE') => Delivery::VEHICLE_CARGO_BIKE,
            ]],
            $options
        );

        $form = $this->createForm(DeliveryType::class, $delivery, $options);

        $routes = $request->attributes->get('routes');

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $delivery = $form->getData();
            $user = $this->getUser();

            $em = $this->getDoctrine()->getManagerForClass('AppBundle:Delivery');

            if ($delivery->getDate() < new \DateTime()) {
                $form->get('date')->addError(new FormError('The date is in the past'));
            }

            if (!$store && !$user->hasRole('ROLE_ADMIN')) {
                $form->addError(new FormError('Unable to create a delivery not linked to a store for a non-admin user'));
            }

            if ($form->isValid()) {

                if ($store) {

                    // if the user is not admin, he cannot override the set pricing
                    if (!$user->hasRole('ROLE_ADMIN')) {
                        $deliveryManager = $this->get('coopcycle.delivery.manager');
                        $price = $deliveryManager->getPrice($delivery, $store->getPricingRuleSet());
                        $delivery->setPrice($price);
                    }
                    $delivery->setStore($store);
                }

                $this->get('delivery_service.default')->calculate($delivery);
                $this->get('coopcycle.delivery.manager')->applyTaxes($delivery);

                $em->persist($delivery);
                $em->flush();

                return $this->redirectToRoute($routes['success']);
            }
        }

        return $this->render("AppBundle:Delivery:form.html.twig", [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'form' => $form->createView(),
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
            'calculate_price_route' => $routes['calculate_price'],
        ]);
    }

    public function pickDeliveryAction($id, Request $request)
    {
        $delivery = $this->getDoctrine()->getRepository(Delivery::class)->find($id);
        $this->accessControl($delivery);

        $delivery->setStatus(Delivery::STATUS_PICKED);
        $this->getDoctrine()->getManagerForClass(Delivery::class)->flush();

        $routes = $this->getDeliveryRoutes();

        return $this->redirectToRoute($routes['list']);
    }

    public function deliverDeliveryAction($id, Delivery $delivery)
    {
        $delivery = $this->getDoctrine()->getRepository(Delivery::class)->find($id);
        $this->accessControl($delivery);

        $delivery->setStatus(Delivery::STATUS_DELIVERED);
        $this->getDoctrine()->getManagerForClass(Delivery::class)->flush();

        $routes = $this->getDeliveryRoutes();

        return $this->redirectToRoute($routes['list']);
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
