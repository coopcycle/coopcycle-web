<?php
declare(strict_types=1);

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;
/**
 * @extends EntityRepository<Organization>
 */
class OrganizationRepository extends EntityRepository
{

    public function reverseFindByOrganization(Organization $org): LocalBusiness|Store|null
    {
        $query = $this->createQueryBuilder('o')
        ->select('s', 'r')
        ->leftJoin(Store::class, 's', 'WITH', 's.organization = o.id')
        ->leftJoin(LocalBusiness::class, 'r', 'WITH', 'r.organization = o.id')
        ->setMaxResults(1)
        ->where('o = :org')
        ->setParameter('org', $org)
        ->getQuery()
        ->getResult();

        $query = array_filter($query);
        if (count($query) === 1) {
            return array_shift($query);
        }
        return null;
    }

}
