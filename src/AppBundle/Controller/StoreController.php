<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Utils\CartTrait;
use AppBundle\Entity\Store;
use AppBundle\Utils\ValidationUtils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 */
class StoreController extends Controller
{

    use CartTrait;

    /**
     * @Route("/store/{id}-{slug}", name="store",
     *   requirements={"id" = "(\d+|__STORE_ID__)", "slug" = "([a-z0-9-]+)"},
     *   defaults={"slug" = ""}
     * )
     * @Template()
     */
    public function indexAction($id, $slug, Request $request)
    {

        $store = $this->getDoctrine()
            ->getRepository(Store::class)
            ->findOneBy(['id' => $id]);

        if (!$store) {
            throw new NotFoundHttpException();
        }

        return array(
            'store' => $store,
            'availabilities' => $store->getAvailabilities(),
        );
    }

    /**
     * @Route("/store/{id}/cart", name="store_cart", methods={"GET", "POST"})
     */
    public function cartAction($id, Request $request)
    {
        $store = $this->getDoctrine()
            ->getRepository(Store::class)->find($id);

        // TODO : check if it the right store

        return $this->handleCartChange($request);
    }

    /**
     * @Route("/store/{id}/cart/product/{code}", name="store_add_product_to_cart", methods={"POST"})
     */
    public function addProductToCartAction($id, $code, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Store::class)->find($id);

        $product = $this->get('sylius.repository.product')
            ->findOneByCode($code);

        $cart = $this->get('sylius.context.cart')->getCart();

        if (!$product->isEnabled()) {
            $errors = [
                'items' => [
                    sprintf('Product %s is not enabled', $product->getCode())
                ]
            ];

            return $this->jsonResponse($cart, $errors);
        }

        if (!$restaurant->hasProduct($product)) {
            $errors = [
                'restaurant' => [
                    sprintf('Unable to add product %s', $product->getCode())
                ]
            ];

            return $this->jsonResponse($cart, $errors);
        }

        $quantity = $request->request->getInt('quantity', 1);

        $cartItem = $this->get('sylius.factory.order_item')->createNew();

        $variantResolver = $this->get('coopcycle.sylius.product_variant_resolver.lazy');

        if (!$product->hasOptions()) {
            $productVariant = $variantResolver->getVariant($product);
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

                return $this->jsonResponse($cart, $errors);
            }

            $productVariant = $variantResolver->getVariantForOptionValues($product, $optionValues);
        }

        $cartItem->setVariant($productVariant);
        $cartItem->setUnitPrice($productVariant->getPrice());

        $this->get('sylius.order_item_quantity_modifier')->modify($cartItem, $quantity);

        $this->get('sylius.order_modifier')->addToOrder($cart, $cartItem);

        $this->get('sylius.manager.order')->persist($cart);
        $this->get('sylius.manager.order')->flush();

        // TODO Find a better way to do this
        $sessionKeyName = $this->getParameter('sylius_cart_restaurant_session_key_name');
        $request->getSession()->set($sessionKeyName, $cart->getId());

        $errors = $this->get('validator')->validate($cart);
        $errors = ValidationUtils::serializeValidationErrors($errors);

        return $this->jsonResponse($cart, $errors);
    }

    /**
     * @Route("/store/{id}/cart/{cartItemId}", methods={"DELETE"}, name="store_remove_from_cart")
     */
    public function removeFromCartAction($id, $cartItemId, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Store::class)->find($id);

        $cart = $this->get('sylius.context.cart')->getCart();
        $cartItem = $this->get('sylius.repository.order_item')->find($cartItemId);

        $this->get('sylius.order_modifier')->removeFromOrder($cart, $cartItem);

        $this->get('sylius.manager.order')->persist($cart);
        $this->get('sylius.manager.order')->flush();

        $errors = $this->get('validator')->validate($cart);
        $errors = ValidationUtils::serializeValidationErrors($errors);

        return $this->jsonResponse($cart, $errors);
    }
}