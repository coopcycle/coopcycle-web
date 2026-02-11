<?php

namespace AppBundle\Controller;

use AppBundle\Annotation\HideSoftDeleted;
use AppBundle\Business\Context as BusinessContext;
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
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use MyCLabs\Enum\Enum;
use Symfony\Contracts\Cache\CacheInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class IndexController extends AbstractController
{
    const EXPIRES_AFTER = 300;

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

    // TODO Add this attribute to Twig components
    #[HideSoftDeleted]
    public function indexAction()
    {
        // Everything is in Twig components
        // @see src/Twig/Components/Homepage.php
        return $this->render('index/index.html.twig');
    }

    #[Route(path: '/cart.json', name: 'cart_json')]
    public function cartAsJsonAction(CartContextInterface $cartContext)
    {
        $cart = $cartContext->getCart();

        $data = [
            'itemsTotal' => $cart->getItemsTotal(),
            'total' => $cart->getTotal(),
        ];

        return new JsonResponse($data);
    }

    #[Route(path: '/CHANGELOG.md', name: 'changelog')]
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
