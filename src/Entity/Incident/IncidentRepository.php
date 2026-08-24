<?php

namespace AppBundle\Entity\Incident;

use Doctrine\ORM\EntityRepository;
/**
 * @extends EntityRepository<Incident>
 */
class IncidentRepository extends EntityRepository {

    public function getFiltersSuggestions(): array {
        $qb = $this->createQueryBuilder('i');

        $stores = $qb->select('DISTINCT s.id, s.name')
            ->join('i.task', 't')
            ->join('t.delivery', 'd')
            ->join('d.store', 's')
            ->where('s.id IS NOT NULL')
            ->getQuery()
            ->getArrayResult();

        $qb = $this->createQueryBuilder('i');
        $restaurants = $qb->select('DISTINCT r.id, r.name')
            ->join('i.task', 't')
            ->join('t.delivery', 'd')
            ->join('d.order', 'o')
            ->join('AppBundle\Entity\Sylius\OrderVendor', 'v', 'WITH', 'v.order = o.id')
            ->join('v.restaurant', 'r')
            ->where('r.id IS NOT NULL')
            ->getQuery()
            ->getArrayResult();

        $qb = $this->createQueryBuilder('i');
        $authors = $qb->select('DISTINCT u.id, u.username')
            ->join('i.createdBy', 'u')
            ->getQuery()
            ->getArrayResult();

        $qb = $this->createQueryBuilder('i');
        $customers = $qb->select('DISTINCT u.id, u.username')
            ->join('i.task', 't')
            ->join('t.delivery', 'd')
            ->join('d.order', 'o')
            ->join('o.customer', 'c')
            ->join('c.user', 'u')
            ->where('u.id IS NOT NULL')
            ->getQuery()
            ->getArrayResult();

        return [
            'stores' => $stores,
            'restaurants' => $restaurants,
            'authors' => $authors,
            'customers' => $customers,
        ];
    }
}
