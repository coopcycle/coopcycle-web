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
        $orderManager = $this->getDoctrine()->getManagerForClass('AppBundle:Order');
        $orderRepository = $orderManager->getRepository('AppBundle:Order');

        $page = $request->query->get('page', 1);

        $qb = $orderRepository->createQueryBuilder('o');

        $qb->select($qb->expr()->count('o'))
           ->where('o.customer = ?1')
           ->setParameter(1, $this->getUser());

        $query = $qb->getQuery();
        $ordersCount = $query->getSingleScalarResult();

        $perPage = 10;

        $pages = ceil($ordersCount / $perPage);
        $offset = $perPage * ($page - 1);

        $orders = $orderRepository->findBy(
            ['customer' => $this->getUser()],
            ['createdAt' => 'DESC'],
            $perPage,
            $offset
        );

        return array(
            'orders' => $orders,
            'page' => $page,
            'pages' => $pages,
        );
    }

    /**
     * @Route("/profile/orders/{id}", name="profile_order")
     * @Template()
     */
    public function orderAction($id, Request $request)
    {
        $order = $this->getDoctrine()->getRepository('AppBundle:Order')
            ->find($id);

        return array(
            'order' => $order,
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
     * @Route("/profile/deliveries", name="profile_courier_deliveries")
     * @Template()
     */
    public function courierDeliveriesAction(Request $request)
    {
        $deliveryTimes = $this->getDoctrine()->getRepository('AppBundle:Order')
            ->getDeliveryTimes($this->getUser());

        $avgDeliveryTime = $this->getDoctrine()->getRepository('AppBundle:Order')
            ->getAverageDeliveryTime($this->getUser());

        $orders = $this->getDoctrine()->getRepository('AppBundle:Order')->findBy(
            ['courier' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return [
            'orders' => $orders,
            'avg_delivery_time' => $avgDeliveryTime,
            'delivery_times' => $deliveryTimes,
        ];
    }
}
