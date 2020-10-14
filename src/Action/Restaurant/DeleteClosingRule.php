<?php

namespace AppBundle\Action\Restaurant;

use AppBundle\Entity\ClosingRule;
use AppBundle\Entity\LocalBusiness;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;

class DeleteClosingRule
{
    public function __construct(EntityManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function __invoke($data)
    {
        $qb = $this->objectManager->getRepository(LocalBusiness::class)
            ->createQueryBuilder('r')
            ->innerJoin('r.closingRules', 'cr')
            ->where('cr.id = :closing_rule')
            ->setParameter('closing_rule', $data)
            ->setMaxResults(1);

        $restaurant = $qb->getQuery()->getOneOrNullResult();

        if ($restaurant) {
            $restaurant->removeClosingRule($data);
        }

        return $data;
    }
}
