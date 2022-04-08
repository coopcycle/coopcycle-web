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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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
    public function indexAction(LocalBusinessRepository $repository, CacheInterface $projectCache,
        TimingRegistry $timingRegistry,
        UrlGeneratorInterface $urlGenerator,
        TranslatorInterface $translator)
    {
        $user = $this->getUser();

        if ($user && ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_RESTAURANT'))) {
            $cacheKeySuffix = $user->getUsername();
        } else {
            $cacheKeySuffix = 'anonymous';
        }

        $sections = [];

        $shopsByType = array_keys($repository->countByType());

        if (count($shopsByType) > 0) {
            $shopType = array_shift($shopsByType);
            $keyForShopType = LocalBusiness::getKeyForType($shopType);
            $cacheKey = sprintf('homepage.%s.%s', $keyForShopType, $cacheKeySuffix);

            [ $shops, $shopsCount ] =
                $this->getItems($repository, $shopType, $projectCache, $cacheKey, $timingRegistry);

            if ($shopsCount > 0) {
                $sections[] = [
                    'title' => $translator->trans(LocalBusiness::getTransKeyForType($shopType)),
                    'shops' => array_slice($shops, 0, self::MAX_SHOPS_PER_SECTION),
                    'view_all_path' => $urlGenerator->generate('shops', [
                        'type' => $keyForShopType,
                    ]),
                ];
            }
        }

        $featured = $repository->findFeatured();
        $featuredIterator = new SortableRestaurantIterator($featured, $timingRegistry);

        if (count($featured) > 0) {
            $sections[] = [
                'title' => $translator->trans('homepage.featured'),
                'shops' => iterator_to_array($featuredIterator),
                'view_all_path' => $urlGenerator->generate('shops', [
                    'category' => 'featured',
                ]),
            ];
        }

        $exclusives = $repository->findExclusives();
        $exclusivesIterator = new SortableRestaurantIterator($exclusives, $timingRegistry);

        if (count($exclusives) > 0) {
            $sections[] = [
                'title' => $translator->trans('homepage.exclusive'),
                'shops' => iterator_to_array($exclusivesIterator),
                'view_all_path' => $urlGenerator->generate('shops', [
                    'category' => 'exclusive',
                ]),
            ];
        }

        $news = $repository->findLatest(self::LATEST_SHOPS_LIMIT);
        $newsIterator = new SortableRestaurantIterator($news, $timingRegistry);

        $sections[] = [
            'title' => $translator->trans('homepage.shops.new'),
            'shops' => iterator_to_array($newsIterator),
            'view_all_path' => $urlGenerator->generate('shops', [
                'category' => 'new',
            ]),
        ];

        $countByCuisine = $repository->countByCuisine();

        foreach ($countByCuisine as $cuisine) {
            if ($cuisine['cnt'] >= self::MIN_SHOPS_PER_CUISINE) {
                $shopsByCuisine = $repository->findByCuisine($cuisine['id']);
                $shopsByCuisineIterator = new SortableRestaurantIterator($shopsByCuisine, $timingRegistry);

                $sections[] = [
                    'title' => $translator->trans($cuisine['name'], [], 'cuisines'),
                    'shops' => iterator_to_array($shopsByCuisineIterator),
                    'view_all_path' => $urlGenerator->generate('shops', [
                        'cuisine' => array($cuisine['name']),
                    ]),
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
