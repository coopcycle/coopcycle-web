<?php

namespace AppBundle\Controller;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Utils\OrderTimeHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\ItemInterface;

class TimingController extends AbstractController
{
    /**
     * @Route("/restaurant/{id}/timing", name="restaurant_fulfillment_timing", methods={"GET"})
     */
    public function fulfillmentTimingAction($id, Request $request,
        EntityManagerInterface $entityManager,
        OrderFactory $orderFactory,
        OrderTimeHelper $orderTimeHelper,
        CacheInterface $projectCache)
    {
        $data = [];

        $deliveryCacheKey = sprintf('restaurant.%d.delivery.timing', $id);
        $collectionCacheKey = sprintf('restaurant.%d.collection.timing', $id);

        $data['delivery'] = $projectCache->get($deliveryCacheKey, function (ItemInterface $item)
            use ($id, $entityManager, $orderFactory, $orderTimeHelper) {

            $restaurant = $entityManager->getRepository(LocalBusiness::class)->find($id);

            if (null === $restaurant || !$restaurant->isFulfillmentMethodEnabled('delivery')) {
                $item->expiresAfter(60 * 60);

                return [];
            }

            $item->expiresAfter(60 * 5);

            $cart = $orderFactory->createForRestaurant($restaurant);
            $cart->setTakeaway(false);

            $timeInfo = $orderTimeHelper->getTimeInfo($cart);

            return [
                'range' => $timeInfo['range'],
                'today' => $timeInfo['today'],
                'fast'  => $timeInfo['fast'],
                'diff'  => $timeInfo['diff'],
            ];
        });

        $data['collection'] = $projectCache->get($collectionCacheKey, function (ItemInterface $item)
            use ($id, $entityManager, $orderFactory, $orderTimeHelper) {

            $restaurant = $entityManager->getRepository(LocalBusiness::class)->find($id);

            if (null === $restaurant || !$restaurant->isFulfillmentMethodEnabled('collection')) {
                $item->expiresAfter(60 * 60);

                return [];
            }

            $item->expiresAfter(60 * 5);

            $cart = $orderFactory->createForRestaurant($restaurant);
            $cart->setTakeaway(true);

            $timeInfo = $orderTimeHelper->getTimeInfo($cart);

            return [
                'range' => $timeInfo['range'],
                'today' => $timeInfo['today'],
                'fast'  => $timeInfo['fast'],
                'diff'  => $timeInfo['diff'],
            ];
        });

        return new JsonResponse($data);
    }
}
