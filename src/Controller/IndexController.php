<?php

namespace AppBundle\Controller;

use AppBundle\Annotation\HideSoftDeleted;
use AppBundle\Controller\Utils\UserTrait;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryForm;
use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
use AppBundle\Form\DeliveryEmbedType;
use Hashids\Hashids;
use MyCLabs\Enum\Enum;
use Symfony\Contracts\Cache\CacheInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\ItemInterface;

class IndexController extends AbstractController
{
    use UserTrait;

    const MAX_RESULTS = 6;
    const EXPIRES_AFTER = 300;

    private function getItems(LocalBusinessRepository $repository, string $type, CacheInterface $cache, string $cacheKey)
    {
        $typeRepository = $repository->withContext($type);

        $itemsIds = $cache->get($cacheKey, function (ItemInterface $item) use ($typeRepository) {

            $item->expiresAfter(self::EXPIRES_AFTER);

            $items = $typeRepository->findAllSorted();

            return array_map(fn(LocalBusiness $lb) => $lb->getId(), $items);
        });

        foreach (array_slice($itemsIds, 0, self::MAX_RESULTS) as $id) {
            if (null === $typeRepository->find($id)) {
                $cache->delete($cacheKey);

                return $this->getItems($repository, $type, $cache, $cacheKey);
            }
        }

        $count = count($itemsIds);
        $items = array_map(
            fn(int $id): LocalBusiness => $typeRepository->find($id),
            array_slice($itemsIds, 0, self::MAX_RESULTS)
        );

        return [ $items, $count ];
    }

    /**
     * @HideSoftDeleted
     */
    public function indexAction(LocalBusinessRepository $repository, CacheInterface $projectCache)
    {
        $user = $this->getUser();

        if ($user && ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_RESTAURANT'))) {
            $cacheKeySuffix = $user->getUsername();
        } else {
            $cacheKeySuffix = 'anonymous';
        }

        [ $restaurants, $restaurantsCount ] =
            $this->getItems($repository, FoodEstablishment::class, $projectCache, sprintf('homepage.restaurants.%s', $cacheKeySuffix));
        [ $stores, $storesCount ] =
            $this->getItems($repository, Store::class, $projectCache, sprintf('homepage.stores.%s', $cacheKeySuffix));

        $hubs = $this->getDoctrine()->getRepository(Hub::class)->findBy([
            'enabled' => true
        ]);

        $qb = $this->getDoctrine()
            ->getRepository(DeliveryForm::class)
            ->createQueryBuilder('f');

        $qb->where('f.showHomepage = :showHomepage');
        $qb->setParameter('showHomepage', ($showHomepage = true));
        $qb->setMaxResults(1);

        $deliveryForm = $qb->getQuery()->getOneOrNullResult();
        $form = null;

        if ($deliveryForm) {
            $form = $this->get('form.factory')->createNamed('delivery', DeliveryEmbedType::class, new Delivery(), [
                'with_weight'      => $deliveryForm->getWithWeight(),
                'with_vehicle'     => $deliveryForm->getWithVehicle(),
                'with_time_slot'   => $deliveryForm->getTimeSlot(),
                'with_package_set' => $deliveryForm->getPackageSet(),
            ]);
        }

        $hashids = new Hashids($this->getParameter('secret'), 12);

        $countZeroWaste = $repository->countZeroWaste();

        return $this->render('index/index.html.twig', array(
            'restaurants' => $restaurants,
            'stores' => $stores,
            'hubs' => $hubs,
            'show_more_restaurants' => $restaurantsCount > self::MAX_RESULTS,
            'show_more_stores' => $storesCount > self::MAX_RESULTS,
            'max_results' => self::MAX_RESULTS,
            'addresses_normalized' => $this->getUserAddresses(),
            'delivery_form' => $form ? $form->createView() : null,
            'hashid' => $deliveryForm ? $hashids->encode($deliveryForm->getId()) : '',
            'zero_waste_count' => $countZeroWaste,
        ));
    }

    /**
     * @Route("/cart.json", name="cart_json")
     */
    public function cartAsJsonAction(CartContextInterface $cartContext)
    {
        $cart = $cartContext->getCart();

        $data = [
            'itemsTotal' => $cart->getItemsTotal(),
            'total' => $cart->getTotal(),
        ];

        return new JsonResponse($data);
    }

    /**
     * @Route("/CHANGELOG.md", name="changelog")
     */
    public function changelogAction()
    {
        $response = new Response(file_get_contents($this->getParameter('kernel.project_dir') . '/CHANGELOG.md'));
        $response->headers->add(['Content-Type' => 'text/markdown']);
        return $response;
    }

    public function redirectToLocaleAction()
    {
        return new RedirectResponse(sprintf('/%s/', $this->getParameter('locale')), 302);
    }
}
