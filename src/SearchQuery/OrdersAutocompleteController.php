<?php

namespace AppBundle\SearchQuery;

use ApiPlatform\Metadata\IriConverterInterface;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\Store;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Autocomplete endpoints backing the "key:value" filters offered by the
 * search bar (see js/app/components/SearchQueryBar) for a given resource -
 * one action per searchable field. To add a new autocompletable field,
 * add an action here (route: /search-query/{resource}/autocomplete:{field})
 * and wire it up as a `type: 'async'` field in the frontend's field config.
 */
class OrdersAutocompleteController extends AbstractController
{
    #[Route(path: '/search-query/orders/autocomplete:owner', name: 'search_query_orders_autocomplete_owner', methods: ['GET'])]
    public function owner(
        Request $request,
        EntityManagerInterface $entityManager,
        LocalBusinessRepository $repository,
        IriConverterInterface $iriConverter
    ): JsonResponse {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $qb = $repository->createQueryBuilder('r');

        $fieldValue = $qb->expr()->literal('%' . $request->query->get('q') . '%');

        $qb->andWhere($qb->expr()->like('r.name', $fieldValue));

        $results = $qb->getQuery()->getResult();

        $hits = [];

        foreach ($results as $restaurant) {
            $hits[] = [
                'label' => $restaurant->getName(),
                'value' => $iriConverter->getIriFromResource($restaurant),
            ];
        }

        $qb = $entityManager->getRepository(Store::class)->createQueryBuilder('s');
        $qb->andWhere($qb->expr()->like('s.name', $fieldValue));

        $results = $qb->getQuery()->getResult();

        foreach ($results as $store) {
            $hits[] = [
                'label' => $store->getName(),
                'value' => $iriConverter->getIriFromResource($store),
            ];
        }

        return new JsonResponse(['hits' => $hits]);
    }
}
