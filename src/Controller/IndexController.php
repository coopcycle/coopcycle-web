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

    const EXPIRES_AFTER = 300;
    const MAX_SECTIONS = 8;
    const MIN_SHOPS_PER_CUISINE = 3;
    const LATEST_SHOPS_LIMIT = 10;
    const MAX_SHOPS_PER_SECTION = 15;

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

        foreach ($itemsIds as $id) {
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
            $itemsIds
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

        $inflector = new EnglishInflector();

        $sections = [];

        $restaurantType = FoodEstablishment::RESTAURANT;
        $keyForRestaurantType = LocalBusiness::getKeyForType($restaurantType);
        $cacheKey = sprintf('homepage.%s.%s', $keyForRestaurantType, $cacheKeySuffix);

        [ $restaurants, $restaurantsCount ] =
            $this->getItems($repository, $restaurantType, $projectCache, $cacheKey, $timingRegistry);

        if ($restaurantsCount > 0) {
            $sections[] = [
                'type' => $restaurantType,
                'shops' => array_slice($restaurants, 0, self::MAX_SHOPS_PER_SECTION),
                'type_key' => $keyForRestaurantType,
                'type_key_plural' => current($inflector->pluralize($keyForRestaurantType)),
            ];
        }

        $featured = $repository->findFeatured();

        if (count($featured) > 0) {
            $sections[] = [
                'type' => null,
                'title' => 'homepage.featured',
                'shops' => $featured,
                'type_key_plural' => null,
                'show_more' => false,
            ];
        }

        $exclusives = $repository->findExclusives();

        if (count($exclusives) > 0) {
            $sections[] = [
                'type' => null,
                'title' => 'homepage.exclusives',
                'shops' => $exclusives,
                'type_key_plural' => null,
                'show_more' => false,
            ];
        }

        $news = $repository->findLatest(self::LATEST_SHOPS_LIMIT);

        $sections[] = [
            'type' => null,
            'title' => 'homepage.shops.new',
            'shops' => $news,
            'type_key_plural' => null,
            'show_more' => false,
        ];

        $countByCuisine = $repository->countByCuisine();

        $cuisines = [];

        foreach ($countByCuisine as $cuisine) {
            if ($cuisine['cnt'] >= self::MIN_SHOPS_PER_CUISINE) {
                $shopsByCuisine = $repository->findByCuisine($cuisine['id']);
                $cuisines[] = [
                    'name' => $cuisine['name'],
                    'shops' => $shopsByCuisine,
                    'view_all_path' => 'restaurants_by_cuisine',
                ];

                if (count($sections) >= self::MAX_SECTIONS) {
                    break;
                }
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
            'cuisines' => $cuisines,
            'hubs' => $hubs,
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
