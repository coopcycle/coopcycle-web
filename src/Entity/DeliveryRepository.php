<?php

namespace AppBundle\Entity;

use Carbon\Carbon;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;
use Hashids\Hashids;

class DeliveryRepository extends EntityRepository
{
    private $secret;

    public function setSecret(string $secret)
    {
        $this->secret = $secret;
    }

    public function getSections(?Store $store = null)
    {
        $today = Carbon::now();

        $after = new \DateTime('+2 days');
        $after->setTime(0, 0, 0);

        $qb = $this->createQueryBuilder('d')
            ->join(TaskCollectionItem::class, 'i', Expr\Join::WITH, 'i.parent = d.id')
            ->join(Task::class, 't', Expr\Join::WITH, 'i.task = t.id')
            ;

        if ($store) {
            $qb
                ->andWhere('d.store = :store')
                ->setParameter('store', $store);
        }

        $qbToday = (clone $qb)
            ->andWhere('t.type = :pickup')
            ->andWhere('t.doneAfter >= :after')
            ->andWhere('t.doneBefore <= :before')
            ->setParameter('pickup', Task::TYPE_PICKUP)
            ->setParameter('after', $today->copy()->hour(0)->minute(0)->second(0))
            ->setParameter('before', $today->copy()->hour(23)->minute(59)->second(59));

        $qbUpcoming = (clone $qb)
            ->andWhere('t.type = :pickup')
            ->andWhere('t.doneAfter >= :after')
            ->setParameter('pickup', Task::TYPE_PICKUP)
            ->setParameter('after', $today->copy()->add(1, 'day')->hour(0)->minute(0)->second(0))
            ->orderBy('t.doneBefore', 'asc')
            ;

        $qbPast = (clone $qb)
            ->andWhere('t.type = :pickup')
            ->andWhere('t.doneBefore < :after')
            ->setParameter('pickup', Task::TYPE_PICKUP)
            ->setParameter('after', $today->copy()->sub(1, 'day')->hour(23)->minute(59)->second(59))
            ;

        return [
            'today' => $qbToday,
            'upcoming' => $qbUpcoming,
            'past' => $qbPast,
        ];
    }

    public function findOneByHashId(string $hashId)
    {
        if (0 === strpos($hashId, 'dlv_')) {
            $hashId = substr($hashId, strlen('dlv_'));
        }

        if (strlen($hashId) !== 32) {

            return null;
        }

        $hashids = new Hashids($this->secret, 32);
        $ids = $hashids->decode($hashId);

        if (count($ids) !== 1) {

            return null;
        }

        $id = current($ids);

        return $this->find($id);
    }
}
