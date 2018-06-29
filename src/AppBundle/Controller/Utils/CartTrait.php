<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\ValidationUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

trait CartTrait
{

    protected function jsonResponse(OrderInterface $cart, array $errors)
    {
        $serializerContext = [
            'groups' => ['order']
        ];

        return new JsonResponse([
            'cart'   => $this->get('serializer')->normalize($cart, 'json', $serializerContext),
            'errors' => $errors,
        ], count($errors) > 0 ? 400 : 200);
    }

    protected function setCartAddress(OrderInterface $cart, Request $request) {

        $addressData = $request->request->get('address');

        $address = $cart->getShippingAddress();
        if (null === $address) {
            $address = new Address();
        }

        $address->setAddressLocality($addressData['addressLocality']);
        $address->setAddressCountry($addressData['addressCountry']);
        $address->setAddressRegion($addressData['addressRegion']);
        $address->setPostalCode($addressData['postalCode']);
        $address->setStreetAddress($addressData['streetAddress']);
        $address->setGeo(new GeoCoordinates($addressData['latitude'], $addressData['longitude']));

        $cart->setShippingAddress($address);

        return $address;
    }

    protected function handleCartChange(Request $request) {
        $cart = $this->get('sylius.context.cart')->getCart();

        if ($request->isMethod('POST')) {

            if ($request->request->has('date')) {
                $cart->setShippedAt(new \DateTime($request->request->get('date')));
            }

            if ($request->request->has('address')) {
                $this->setCartAddress($cart, $request);
            }

            $this->get('sylius.manager.order')->persist($cart);
            $this->get('sylius.manager.order')->flush();

            // TODO Find a better way to do this
            $sessionKeyName = $this->getParameter('sylius_cart_restaurant_session_key_name');
            $request->getSession()->set($sessionKeyName, $cart->getId());
        }

        $errors = $this->get('validator')->validate($cart);
        $errors = ValidationUtils::serializeValidationErrors($errors);

        return $this->jsonResponse($cart, $errors);
    }


}