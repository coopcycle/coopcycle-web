<?php

namespace AppBundle\Controller\Admin;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Entity\Sylius\Product;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CustomerController extends AbstractController
{
    private const ITEMS_PER_PAGE = 20;

    #[Route('/admin/customers/{id}', name: 'admin_customer', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function __invoke(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        PaginatorInterface $paginator,
        #[Autowire(service: 'recommender.client')] HttpClientInterface $recommenderClient,
    ): Response {
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

        $recommendedRestaurants = $this->fetchRecommendations(
            $id, 'restaurant', $recommenderClient, $em, LocalBusiness::class
        );
        $recommendedProducts = $this->fetchRecommendations(
            $id, 'product', $recommenderClient, $em, Product::class
        );

        return $this->render('admin/customer.html.twig', [
            'customer'                 => $customer,
            'orders'                   => $orders,
            'number_of_orders'         => $insights['numberOfOrders'],
            'average_order_total'      => $insights['averageOrderTotal'],
            'first_ordered_at'         => $insights['firstOrderedAt'],
            'last_ordered_at'          => $insights['lastOrderedAt'],
            'favorite_restaurant'      => $insights['favoriteRestaurant'],
            'recommended_restaurants'  => $recommendedRestaurants,
            'recommended_products'     => $recommendedProducts,
        ]);
    }

    private function fetchRecommendations(
        int $customerId,
        string $type,
        HttpClientInterface $recommenderClient,
        EntityManagerInterface $em,
        string $entityClass,
    ): array {
        try {
            $response = $recommenderClient->request('GET', '/recommendations', [
                'query' => ['customer' => "/api/customers/{$customerId}", 'type' => $type, 'n' => 5],
            ]);
            $iris = $response->toArray()['recommendations'] ?? [];
        } catch (\Throwable) {
            return [];
        }

        if (empty($iris)) {
            return [];
        }

        $ids = array_filter(array_map(fn(string $iri) => (int) basename($iri), $iris));

        return $em->getRepository($entityClass)->findBy(['id' => $ids, 'enabled' => true]);
    }
}
