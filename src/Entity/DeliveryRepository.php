<?php

namespace AppBundle\Entity;

use Carbon\Carbon;
use DateTimeInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;
use Hashids\Hashids;
use Psonic\Client as SonicClient;
use Symfony\Component\Intl\Languages;

class DeliveryRepository extends EntityRepository
{
    private $secret;
    private $sonicClient;
    private $sonicSecretPassword;
    private $sonicNamespace;
    /**
     * @return void
     */
    public function setSecret(string $secret)
    {
        $this->secret = $secret;
    }
    /**
     * @return void
     */
    public function setSonicClient(SonicClient $client)
    {
        $this->sonicClient = $client;
    }
    /**
     * @return void
     */
    public function setSonicSecretPassword(string $password)
    {
        $this->sonicSecretPassword = $password;
    }
    /**
     * @return void
     */
    public function setSonicNamespace(string $namespace)
    {
        $this->sonicNamespace = $namespace;
    }

    public function createQueryBuilderWithTasks(): QueryBuilder
    {
        return $this->createQueryBuilder('d')
            ->join(TaskCollectionItem::class, 'i', Expr\Join::WITH, 'i.parent = d.id')
            ->join(Task::class, 't', Expr\Join::WITH, 'i.task = t.id')
            ;
    }

    public function today(QueryBuilder $qb): QueryBuilder
    {
        $today = Carbon::now();

        return (clone $qb)
            ->andWhere('t.type = :pickup')
            ->andWhere('t.doneBefore >= :after')
            ->andWhere('t.doneAfter <= :before')
            ->setParameter('pickup', Task::TYPE_PICKUP)
            ->setParameter('after', $today->clone()->startOfDay())
            ->setParameter('before', $today->clone()->endOfDay())
            ;
    }

    public function upcoming(QueryBuilder $qb): QueryBuilder
    {
        $today = Carbon::now();

        return (clone $qb)
            ->andWhere('t.type = :pickup')
            ->andWhere('t.doneAfter > :endOfToday')
            ->setParameter('pickup', Task::TYPE_PICKUP)
            ->setParameter('endOfToday', $today->clone()->endOfDay())
            ->orderBy('t.doneBefore', 'asc')
            ;
    }

    public function past(QueryBuilder $qb): QueryBuilder
    {
        $today = Carbon::now();

        return (clone $qb)
            ->andWhere('t.type = :pickup')
            ->andWhere('t.doneBefore < :startOfToday')
            ->setParameter('pickup', Task::TYPE_PICKUP)
            ->setParameter('startOfToday', $today->clone()->startOfDay())
            ;
    }
    public function createdAtRange(QueryBuilder $qb, \DateTimeInterface $start, \DateTimeInterface $end): QueryBuilder
    {
        return $qb
            ->andWhere('d.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ;
    }

    /**
     * @return null|object
     */
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
    /**
     * @return void
     */
    public function searchWithSonic(QueryBuilder $qb, string $q, string $locale, ?Store $store = null)
    {
        $search = new \Psonic\Search($this->sonicClient);
        $search->connect($this->sonicSecretPassword);

        $collection = (null !== $store) ? sprintf('store:%d:deliveries', $store->getId()) : 'store:*:deliveries';

        $ids = $search->query($collection, $this->sonicNamespace,
            // We use $limit = 100, which is the value of query_limit_maximum in sonic.cfg
            $q, $limit = 100, $offset = null, Languages::getAlpha3Code($locale));

        $search->disconnect();

        $ids = array_filter($ids);

        $qb
            ->andWhere('d.id IN (:ids)')
            ->setParameter('ids', $ids);
    }
    /**
     * @return array<Delivery>
     */
    /**
     * Deliveries of a store having at least one proof of delivery (a task image)
     * uploaded within the given date range.
     *
     * The date range is matched against the images' creation date, *not* against
     * the tasks' time windows, so that deliveries spanning several days
     * (or completed later than planned) are not left out.
     */
    private function createProofsOfDeliveryQueryBuilder(Store|int $store, DateTimeInterface $from, DateTimeInterface $to): QueryBuilder
    {
        return $this->createQueryBuilderWithTasks()
            ->join('t.images', 'p')
            ->andWhere('d.store = :store')
            ->andWhere('t.type = :dropoff')
            ->andWhere('p.createdAt BETWEEN :from AND :to')
            ->setParameter('dropoff', Task::TYPE_DROPOFF)
            ->setParameter('store', $store)
            ->setParameter('from', $from)
            ->setParameter('to', $to);
    }

    /**
     * @return Delivery[]
     */
    public function findDeliveriesWithProofsOfDelivery(Store|int $store, DateTimeInterface $from, DateTimeInterface $to): array
    {
        return $this->createProofsOfDeliveryQueryBuilder($store, $from, $to)
            ->select('DISTINCT d')
            ->addOrderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countDeliveriesWithProofsOfDelivery(Store|int $store, DateTimeInterface $from, DateTimeInterface $to): int
    {
        return (int) $this->createProofsOfDeliveryQueryBuilder($store, $from, $to)
            ->select('COUNT(DISTINCT d.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByLoUri(string $loUri): ?Delivery
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "SELECT DISTINCT d.id FROM delivery d
            JOIN task_collection_item tci ON tci.parent = d.id
            JOIN task t ON tci.task_id = t.id
            WHERE t.metadata->>'rdc_lo_uri' = :loUri";

        $result = $conn->executeQuery($sql, ['lo_uri' => $loUri])->fetchOne();

        if ($result === false) {
            return null;
        }

        return $this->find($result);
    }

}
