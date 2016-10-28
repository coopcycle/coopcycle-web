<?php

namespace AppBundle\Controller;

use AppBundle\Utils\Cart;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\DeliveryAddress;
use AppBundle\Utils\OrderStatus;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use League\Geotools\Geotools;
use League\Geotools\Coordinate\Coordinate;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class ProfileController extends Controller
{
    use DoctrineTrait;

    /**
     * @Route("/profile/orders", name="profile_orders")
     * @Template()
     */
    public function ordersAction(Request $request)
    {
        $orderManager = $this->getDoctrine()->getManagerForClass('AppBundle\\Entity\\Order');
        $orderRepository = $orderManager->getRepository('AppBundle\\Entity\\Order');

        $orders = $orderRepository->findBy(array('customer' => $this->getUser()));

        return array(
            'orders' => $orders,
        );
    }

    /**
     * @Route("/profile/addresses", name="profile_addresses")
     * @Template()
     */
    public function addressesAction(Request $request)
    {
        $manager = $this->getDoctrine()->getManagerForClass('AppBundle\\Entity\\DeliveryAddress');
        $repository = $manager->getRepository('AppBundle\\Entity\\DeliveryAddress');

        $addresses = $repository->findBy(array('customer' => $this->getUser()));

        return array(
            'addresses' => $addresses,
        );
    }

    /**
     * @Route("/profile/addresses/new", name="profile_address_new")
     * @Template()
     */
    public function newAddressAction(Request $request)
    {
        $address = new DeliveryAddress();

        $form = $this->createFormBuilder($address)
            ->add('name', TextType::class)
            ->add('streetAddress', TextType::class)
            ->add('postalCode', TextType::class)
            ->add('save', SubmitType::class, array('label' => 'Sauvegarder'))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $address = $form->getData();
            $address->setCustomer($this->getUser());

            $manager = $this->getDoctrine()->getManagerForClass('AppBundle\\Entity\\DeliveryAddress');
            $manager->persist($address);
            $manager->flush();

            return $this->redirectToRoute('profile_addresses');
        }

        return array(
            'form' => $form->createView(),
        );
    }

    /**
     * @Template()
     */
    public function restaurantOrdersAction(Request $request)
    {
        $restaurants = $this->getUser()->getRestaurants();

        $orders = $this->getRepository('Order')->findByRestaurants($restaurants);

        $nextStatus = array();
        foreach ($orders as $order) {
            $nextStatus[$order->getId()] = OrderStatus::getNext($order);
        }

        return array(
            'orders' => $orders,
            'next_status' => $nextStatus
        );
    }

    /**
     * @Template()
     */
    public function restaurantOrderAction($id, Request $request)
    {
        $order = $this->getRepository('Order')->find($id);

        if ($request->isMethod('POST')) {
            $status = $request->request->get('status');
            $order->setStatus($status);
            $this->getManager('Order')->flush();

            return $this->redirectToRoute('profile_restaurant_orders');
        }

        return array(
            'order' => $order
        );
    }
}
