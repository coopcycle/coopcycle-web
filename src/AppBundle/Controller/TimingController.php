<?php

namespace AppBundle\Controller;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Utils\OrderTimeHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TimingController extends AbstractController
{
    /**
     * @Route("/restaurant/{id}/timing", name="restaurant_fulfillment_timing", methods={"GET"})
     */
    public function fulfillmentTimingAction($id, Request $request,
        OrderFactory $orderFactory,
        OrderTimeHelper $orderTimeHelper,
        CacheInterface $appCache)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(LocalBusiness::class)->find($id);

        if (!$restaurant) {
            throw new NotFoundHttpException();
        }

        $data = [];

        $deliveryCacheKey = sprintf('restaurant.%d.delivery.timing', $restaurant->getId());
        $collectionCacheKey = sprintf('restaurant.%d.collection.timing', $restaurant->getId());

        if ($restaurant->isFulfillmentMethodEnabled('delivery')) {

            $data['delivery'] = $appCache->get($deliveryCacheKey, function (ItemInterface $item)
                use ($restaurant, $orderFactory, $orderTimeHelper) {

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
        }

        if ($restaurant->isFulfillmentMethodEnabled('collection')) {

            $data['collection'] = $appCache->get($collectionCacheKey, function (ItemInterface $item)
                use ($restaurant, $orderFactory, $orderTimeHelper) {

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
        }

        return new JsonResponse($data);
    }
}
