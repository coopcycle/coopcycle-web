<?php

namespace AppBundle\Controller;

use AppBundle\Entity\LocalBusinessRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use TeamTNT\TNTSearch\TNTSearch;
use TeamTNT\TNTSearch\Stemmer\PorterStemmer;

class SearchController extends AbstractController
{
    /**
     * @Route("/search/restaurants", name="search_restaurants")
     */
    public function restaurantsAction(Request $request, Connection $connection, LocalBusinessRepository $repository)
    {
        $tnt = new TNTSearch;

        $projectDir = $this->getParameter('kernel.project_dir');
        $tntSearchDir = $projectDir . '/var/tntsearch';

        if (!file_exists($tntSearchDir)) {
            mkdir($tntSearchDir, 0755);
        }

        if (!file_exists($tntSearchDir . '/restaurants.index')) {
            return new JsonResponse(['hits' => []]);
        }

        $tnt->loadConfig([
            'driver'    => 'pgsql',
            'host'      => $connection->getHost(),
            'database'  => $connection->getDatabase(),
            'username'  => $connection->getUsername(),
            'password'  => $connection->getPassword(),
            'storage'   => $tntSearchDir,
            'stemmer'   => PorterStemmer::class // optional
        ]);

        $tnt->selectIndex('restaurants.index');

        $res = $tnt->search($request->query->get('q'), 5);

        if ($res['hits'] === 0) {
            return new JsonResponse(['hits' => []]);
        }

        // to display the results you need an additional query against your application database
        // SELECT * FROM articles WHERE id IN $res ORDER BY FIELD(id, $res);
        $qb = $repository->createQueryBuilder('r');
        $qb->add('where', $qb->expr()->in('r.id', $res['ids']));

        // TODO Filter disabled restaurants
        // TODO Use usort to reorder, ORDER BY FIELD(...) is only for MySQL

        $results = $qb->getQuery()->getResult();

        $hits = [];
        foreach ($results as $result) {
            $hits[] = [
                'name' => $result->getName(),
            ];
        }

        return new JsonResponse(['hits' => $hits]);
    }
}
