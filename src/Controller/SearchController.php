<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Service\Geocoder;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psonic\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Intl\Languages;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SearchController extends AbstractController
{
    /**
     * @Route("/search/restaurants", name="search_restaurants")
     */
    public function restaurantsAction(Request $request,
        LocalBusinessRepository $repository,
        Client $client,
        UrlGeneratorInterface $urlGenerator)
    {
        $locale = $request->getLocale();

        $search = new \Psonic\Search($client);
        $search->connect($this->getParameter('sonic_secret_password'));

        $ids = $search->query('restaurants', $this->getParameter('sonic_namespace'),
            $request->query->get('q'), $limit = null, $offset = null, Languages::getAlpha3Code($locale));

        $search->disconnect();

        $ids = array_filter($ids);

        if (count($ids) === 0) {
            return new JsonResponse(['hits' => []]);
        }

        // to display the results you need an additional query against your application database
        // SELECT * FROM articles WHERE id IN $res ORDER BY FIELD(id, $res);
        $qb = $repository->createQueryBuilder('r');
        $qb->add('where', $qb->expr()->in('r.id', $ids));

        // TODO Filter disabled restaurants
        // TODO Use usort to reorder

        $results = $qb->getQuery()->getResult();

        $hits = [];
        foreach ($results as $result) {
            $hits[] = [
                'name' => $result->getName(),
                'url' => $trackingUrl = $urlGenerator->generate('restaurant', [
                    'id' => $result->getId(),
                ])
            ];
        }

        return new JsonResponse(['hits' => $hits]);
    }

    /**
     * @Route("/search/deliveries", name="search_deliveries")
     */
    public function deliveriesAction(Request $request,
        EntityManagerInterface $entityManager,
        Client $client,
        UrlGeneratorInterface $urlGenerator)
    {
        $user = $this->getUser();

        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STORE')) {
            throw $this->createAccessDeniedException();
        }

        if ($user->hasRole('ROLE_STORE') && !$request->attributes->has('_store')) {
            throw $this->createAccessDeniedException();
        }

        $locale = $request->getLocale();

        $search = new \Psonic\Search($client);
        $search->connect($this->getParameter('sonic_secret_password'));

        $store = $request->attributes->get('_store');

        $collectionName = $user->hasRole('ROLE_ADMIN') ?
            'store:*:deliveries' : sprintf('store:%d:deliveries', $store->getId());

        $ids = $search->query($collectionName, $this->getParameter('sonic_namespace'),
            $request->query->get('q'), $request->query->get('limit'), $offset = null, Languages::getAlpha3Code($locale));

        $search->disconnect();

        $ids = array_filter($ids);

        if (count($ids) === 0) {
            return new JsonResponse(['hits' => []]);
        }

        $repository = $entityManager->getRepository(Delivery::class);

        // to display the results you need an additional query against your application database
        // SELECT * FROM articles WHERE id IN $res ORDER BY FIELD(id, $res);
        $qb = $repository->createQueryBuilder('r');
        $qb->add('where', $qb->expr()->in('r.id', $ids));

        // TODO Filter disabled restaurants
        // TODO Use usort to reorder

        $results = $qb->getQuery()->getResult();

        $hits = [];
        foreach ($results as $result) {
            $hits[] = $this->get('serializer')->normalize($result, 'jsonld', [
                'groups' => ['delivery', 'task', 'address']
            ]);
        }

        return new JsonResponse(['hits' => $hits]);
    }

    /**
     * @Route("/search/geocode", name="search_geocode")
     */
    public function geocodeAction(Request $request, Geocoder $geocoder)
    {
        if ($address = $geocoder->geocode($request->query->get('address'))) {

            return new JsonResponse([
                'latitude' => $address->getGeo()->getLatitude(),
                'longitude' => $address->getGeo()->getLongitude(),
            ]);
        }

        return new JsonResponse([], 400);
    }
}
