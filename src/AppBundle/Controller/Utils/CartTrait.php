<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Entity\Restaurant;
use AppBundle\Utils\ValidationUtils;
use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

trait CartTrait
{
    abstract protected function prepareCartContext(Request $request, Restaurant $restaurant);

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

        // TODO Avoid duplicating adresses
        // If the user is authenticated,
        // try to match the address with an existing address

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

    public function cartAction($id, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)->find($id);

        $sessionKeyName = $this->prepareCartContext($request, $restaurant);
        $cart = $this->get('sylius.context.cart')->getCart();

        if ($cart->getRestaurant() !== $restaurant) {
            $errors = [
                'restaurant' => [
                    sprintf('Restaurant mismatch')
                ]
            ];

            return $this->jsonResponse($cart, $errors);
        }

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
            $request->getSession()->set($sessionKeyName, $cart->getId());
        }

        $errors = $this->get('validator')->validate($cart);
        $errors = ValidationUtils::serializeValidationErrors($errors);

        return $this->jsonResponse($cart, $errors);
    }

    public function addMenuItemToCartAction($restaurantId, $menuItemId, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)->find($restaurantId);

        $sessionKeyName = $this->prepareCartContext($request, $restaurant);
        $cart = $this->get('sylius.context.cart')->getCart();

        $menuItem = $this->getDoctrine()
            ->getRepository(MenuItem::class)->find($menuItemId);

        if (!$menuItem->isAvailable()) {
            $errors = [
                'items' => [
                    sprintf('Item %s is not available', $menuItem->getName())
                ]
            ];

            return $this->jsonResponse($cart, $errors);
        }

        if ($menuItem->getRestaurant() !== $cart->getRestaurant()) {
            $errors = [
                'restaurant' => [
                    sprintf('Unable to add %s', $menuItem->getName())
                ]
            ];

            return $this->jsonResponse($cart, $errors);
        }

        $quantity = $request->request->getInt('quantity', 1);

        $productVariantRepository = $this->get('sylius.repository.product_variant');
        $productVariantFactory = $this->get('sylius.factory.product_variant');
        $orderItemFactory = $this->get('sylius.factory.order_item');

        $cartItem = $orderItemFactory->createNew();

        $modifiers = [];
        // FIXME
        // Here we should check if the product actually has options
        // For example using Product::isSimple
        if ($request->request->has('modifiers')) {

            $modifiers = $this->resolveModifiers($menuItem, $request->request->get('modifiers'));
            $productVariant = $productVariantRepository->findOneByMenuItemWithModifiers($menuItem, $modifiers);

            if (!$productVariant) {
                $productVariant = $productVariantFactory->createForMenuItemWithModifiers($menuItem, $modifiers);
                $productVariantRepository->add($productVariant);
            }

        } else {
            $productVariant = $productVariantRepository->findOneByMenuItem($menuItem);
        }

        $cartItem->setVariant($productVariant);
        $cartItem->setUnitPrice($productVariant->getPrice());

        if (!empty($modifiers)) {
            $this->addModifiersAdjustments($cartItem, $modifiers);
        }

        $this->get('sylius.order_item_quantity_modifier')->modify($cartItem, $quantity);

        $this->get('sylius.order_modifier')->addToOrder($cart, $cartItem);

        $this->get('sylius.manager.order')->persist($cart);
        $this->get('sylius.manager.order')->flush();

        // TODO Find a better way to do this
        $request->getSession()->set($sessionKeyName, $cart->getId());

        $errors = $this->get('validator')->validate($cart);
        $errors = ValidationUtils::serializeValidationErrors($errors);

        return $this->jsonResponse($cart, $errors);
    }

    /**
     * @Route("/restaurant/{restaurantId}/cart/{cartItemId}", methods={"DELETE"}, name="restaurant_remove_from_cart")
     */
    public function removeFromCartAction($restaurantId, $cartItemId, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)->find($restaurantId);

        $sessionKeyName = $this->prepareCartContext($request, $restaurant);
        $cart = $this->get('sylius.context.cart')->getCart();

        $cartItem = $this->get('sylius.repository.order_item')->find($cartItemId);
        if ($cartItem) {
            $this->get('sylius.order_modifier')->removeFromOrder($cart, $cartItem);

            $this->get('sylius.manager.order')->persist($cart);
            $this->get('sylius.manager.order')->flush();
        }

        $errors = $this->get('validator')->validate($cart);
        $errors = ValidationUtils::serializeValidationErrors($errors);

        return $this->jsonResponse($cart, $errors);
    }
}
