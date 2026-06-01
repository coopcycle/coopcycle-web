<?php

namespace AppBundle\Controller\Admin;

use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CustomerController extends AbstractController
{
    private const ITEMS_PER_PAGE = 20;

    #[Route('/admin/customers/{id}', name: 'admin_customer', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function __invoke(int $id, Request $request, EntityManagerInterface $em, PaginatorInterface $paginator): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $customer = $em->find(Customer::class, $id);
        if (null === $customer) {
            throw $this->createNotFoundException();
        }

        /** @var OrderRepository */
        $orderRepo = $em->getRepository(Order::class);

        $ordersQb = $orderRepo->createQueryBuilder('o')
            ->andWhere('o.customer = :customer')
            ->andWhere('o.state != :cart')
            ->setParameter('customer', $customer)
            ->setParameter('cart', Order::STATE_CART)
            ->orderBy('o.createdAt', 'DESC');

        $orders = $paginator->paginate(
            $ordersQb,
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE
        );

        $insights = $orderRepo->getCustomerInsights($customer);

        return $this->render('admin/customer.html.twig', [
            'customer'            => $customer,
            'orders'              => $orders,
            'number_of_orders'    => $insights['numberOfOrders'],
            'average_order_total' => $insights['averageOrderTotal'],
            'first_ordered_at'    => $insights['firstOrderedAt'],
            'last_ordered_at'     => $insights['lastOrderedAt'],
            'favorite_restaurant' => $insights['favoriteRestaurant'],
        ]);
    }
}
