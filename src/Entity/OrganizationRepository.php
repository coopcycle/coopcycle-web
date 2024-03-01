<?php
declare(strict_types=1);

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;

class OrganizationRepository extends EntityRepository
{

    public function reverseFindByOrganizarionID($org)
    {
        $query = $this->createQueryBuilder('o')
        ->select('s.id as store_id', 'r.id as restaurant_id')
        ->leftJoin(Store::class, 's', 'WITH', 's.organization = o.id')
        ->leftJoin(LocalBusiness::class, 'r', 'WITH', 'r.organization = o.id')
        ->setMaxResults(1)
        ->where('o = :org')
        ->setParameter('org', $org);

        return $query->getQuery()->getResult();
    }

}
