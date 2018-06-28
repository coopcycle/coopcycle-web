<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Utils\CartTrait;
use AppBundle\Entity\Store;
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

        // This will be used by RestaurantCartContext
        $request->getSession()->set('storeId', $id);

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
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)->find($id);

        // This will be used by RestaurantCartContext
        $request->getSession()->set('storeId', $id);

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
            $sessionKeyName = $this->getParameter('sylius_restaurant_cart_session_key_name');
            $request->getSession()->set($sessionKeyName, $cart->getId());
        }

        $errors = $this->get('validator')->validate($cart);
        $errors = ValidationUtils::serializeValidationErrors($errors);

        return $this->jsonResponse($cart, $errors);
    }
}