<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Exception\CartException;
use AppBundle\Utils\ValidationUtils;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItemInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

trait CartTrait
{
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

    protected function cartJsonResponse(OrderInterface $cart, array $errors)
    {
        $serializerContext = [
            'groups' => ['order']
        ];

        return new JsonResponse([
            'cart'   => $this->get('serializer')->normalize($cart, 'json', $serializerContext),
            'errors' => $errors,
        ], count($errors) > 0 ? 400 : 200);
    }

    protected function addProductToCart(OrderInterface $cart, $code, Request $request)
    {
        $product = $this->get('sylius.repository.product')
            ->findOneByCode($code);

        // $cart = $this->get('sylius.context.cart')->getCart();

        if (!$product->isEnabled()) {
            $errors = [
                'items' => [
                    sprintf('Product %s is not enabled', $product->getCode())
                ]
            ];

            throw new CartException($errors);
        }

        // if (!$restaurant->hasProduct($product)) {
        //     $errors = [
        //         'restaurant' => [
        //             sprintf('Unable to add product %s', $product->getCode())
        //         ]
        //     ];

        //     throw new CartException($errors);
        // }

        $quantity = $request->request->getInt('quantity', 1);

        $cartItem = $this->get('sylius.factory.order_item')->createNew();

        if (!$product->hasOptions()) {
            $productVariant = $this->get('sylius.product_variant_resolver.default')->getVariant($product);
        } else {

            $productOptionValueRepository = $this->get('sylius.repository.product_option_value');
            $options = $request->request->get('options');

            $optionValues = [];
            foreach ($options as $optionCode => $optionValueCode) {
                $optionValue = $productOptionValueRepository->findOneByCode($optionValueCode);
                $optionValues[] = $optionValue;
            }

            $nonExistingOption = $this->matchNonExistingOption($product, $optionValues);
            if (null !== $nonExistingOption) {
                $errors = [
                    'items' => [
                        sprintf('Product %s does not have option %s', $product->getCode(), $nonExistingOption->getCode())
                    ]
                ];

                throw new CartException($errors);
            }

            $productVariant = $this->resolveProductVariant($product, $optionValues);

            // Lazily create a product variant
            // As we "hide" product variants, some variants may not have been created yet
            // At this step, we are pretty sure the options are valid
            if (!$productVariant) {
                $productVariant = $this->createProductVariant($product, $optionValues);
                $this->get('sylius.repository.product_variant')->add($productVariant);
            }
        }

        $cartItem->setVariant($productVariant);
        $cartItem->setUnitPrice($productVariant->getPrice());

        $this->get('sylius.order_item_quantity_modifier')->modify($cartItem, $quantity);

        $this->get('sylius.order_modifier')->addToOrder($cart, $cartItem);

        // $this->get('sylius.manager.order')->persist($cart);
        // $this->get('sylius.manager.order')->flush();

        // TODO Find a better way to do this
        // $sessionKeyName = $this->getParameter('sylius_cart_restaurant_session_key_name');
        // $request->getSession()->set($sessionKeyName, $cart->getId());

        // $errors = $this->get('validator')->validate($cart);
        // $errors = ValidationUtils::serializeValidationErrors($errors);

        // return $this->cartJsonResponse($cart, $errors);
    }

    protected function removeItemFromCart(OrderInterface $cart, OrderItemInterface $item)
    {
        $this->get('sylius.order_modifier')->removeFromOrder($cart, $item);
    }
}
