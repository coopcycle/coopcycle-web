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
use AppBundle\Service\TimingRegistry;
use AppBundle\Utils\SortableRestaurantIterator;
use Cocur\Slugify\SlugifyInterface;
use Hashids\Hashids;
use MyCLabs\Enum\Enum;
use Symfony\Contracts\Cache\CacheInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\String\Inflector\EnglishInflector;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\ItemInterface;

class IndexController extends AbstractController
{
    use UserTrait;

    const MAX_RESULTS = 6;
    const EXPIRES_AFTER = 300;
    const ITEMS_PER_ROW = 3;

    private function getItems(LocalBusinessRepository $repository, string $type, CacheInterface $cache, string $cacheKey, TimingRegistry $timingRegistry)
    {
        $typeRepository = $repository->withTypeFilter($type);

        $itemsIds = $cache->get($cacheKey, function (ItemInterface $item) use ($typeRepository, $timingRegistry) {

            $item->expiresAfter(self::EXPIRES_AFTER);

            $items = $typeRepository->findAllForType();

            $iterator = new SortableRestaurantIterator($items, $timingRegistry);
            $items = iterator_to_array($iterator);

            return array_map(fn(LocalBusiness $lb) => $lb->getId(), $items);
        });

        $slicedItemsIds = array_slice($itemsIds, 0, self::MAX_RESULTS);

        foreach ($slicedItemsIds as $id) {
            // If one of the items can't be found (probably because it's disabled),
            // we invalidate the cache
            if (null === $typeRepository->find($id)) {
                $cache->delete($cacheKey);

                return $this->getItems($repository, $type, $cache, $cacheKey, $timingRegistry);
            }
        }

        $count = count($itemsIds);
        $items = array_map(
            fn(int $id): LocalBusiness => $typeRepository->find($id),
            $slicedItemsIds
        );

        return [ $items, $count ];
    }

    /**
     * @HideSoftDeleted
     */
    public function indexAction(LocalBusinessRepository $repository, CacheInterface $projectCache, SlugifyInterface $slugify,
        TimingRegistry $timingRegistry)
    {
        $user = $this->getUser();

        if ($user && ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_RESTAURANT'))) {
            $cacheKeySuffix = $user->getUsername();
        } else {
            $cacheKeySuffix = 'anonymous';
        }

        $countByType = $repository->countByType();
        $types = array_keys($countByType);

        $typesWithEnoughShops = [];
        foreach ($countByType as $type => $countForType) {
            if ($countForType >= self::ITEMS_PER_ROW || $countForType >= (self::ITEMS_PER_ROW * 2)) {
                $typesWithEnoughShops[] = $type;
            }
        }

        $inflector = new EnglishInflector();

        $sections = [];

        if (count($typesWithEnoughShops) > 0) {

            foreach ($typesWithEnoughShops as $type) {

                $keyForType = LocalBusiness::getKeyForType($type);

                $cacheKey = sprintf('homepage.%s.%s', $keyForType, $cacheKeySuffix);

                [ $shops, $shopsCount ] =
                    $this->getItems($repository, $type, $projectCache, $cacheKey, $timingRegistry);

                $sections[] = [
                    'type' => $type,
                    'shops' => $shops,
                    'type_key' => $keyForType,
                    'type_key_plural' => current($inflector->pluralize($keyForType)),
                ];

                if (count($sections) >= 3) {
                    break;
                }
            }

        } else {

            $shops = $repository->withoutTypeFilter()->findAllForType();

            $iterator = new SortableRestaurantIterator($shops, $timingRegistry);
            $shops = iterator_to_array($iterator);

            if (count($shops) > 0) {

                $types = [];
                foreach ($shops as $shop) {
                    $types[] = $shop->getType();
                }
                $types = array_unique($types);

                $keysForTypes = array_map(fn ($t) => LocalBusiness::getKeyForType($t), $types);

                if (count($shops) >= (self::ITEMS_PER_ROW * 2)) {
                    $shops = array_slice($shops, 0, (self::ITEMS_PER_ROW * 2));
                } elseif (count($shops) >= self::ITEMS_PER_ROW) {
                    $shops = array_slice($shops, 0, self::ITEMS_PER_ROW);
                }

                $sections[] = [
                    'type' => null,
                    'types' => $types,
                    'shops' => $shops,
                    'type_key' => null,
                    'type_key_plural' => null,
                ];
            }

        }

        $hubs = $this->getDoctrine()->getRepository(Hub::class)->findBy([
            'enabled' => true
        ]);

        $deliveryForm = $this->getDeliveryForm();

        $hashids = new Hashids($this->getParameter('secret'), 12);

        $countZeroWaste = $repository->countZeroWaste();

        return $this->render('index/index.html.twig', array(
            'sections' => $sections,
            'hubs' => $hubs,
            'max_results' => self::MAX_RESULTS,
            'addresses_normalized' => $this->getUserAddresses(),
            'delivery_form' => $deliveryForm ?
                $this->getDeliveryFormForm($deliveryForm)->createView() : null,
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

    private function getDeliveryForm(): ?DeliveryForm
    {
        $qb = $this->getDoctrine()
            ->getRepository(DeliveryForm::class)
            ->createQueryBuilder('f');

        $qb->where('f.showHomepage = :showHomepage');
        $qb->setParameter('showHomepage', ($showHomepage = true));
        $qb->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    private function getDeliveryFormForm(?DeliveryForm $deliveryForm = null)
    {
        if ($deliveryForm) {

            return $this->get('form.factory')->createNamed('delivery', DeliveryEmbedType::class, new Delivery(), [
                'with_weight'      => $deliveryForm->getWithWeight(),
                'with_vehicle'     => $deliveryForm->getWithVehicle(),
                'with_time_slot'   => $deliveryForm->getTimeSlot(),
                'with_package_set' => $deliveryForm->getPackageSet(),
            ]);
        }

        return null;
    }
}
