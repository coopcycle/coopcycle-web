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

    /**
     * The query builder returned by createQueryBuilderWithTasks() joins *every*
     * task of a delivery, so a delivery with a pickup & a dropoff yields 2 rows,
     * and a multi-point delivery even more.
     *
     * This restricts the join to a single task: the first pickup, i.e. the one
     * Delivery::getPickup() returns & the listings display. One row per
     * delivery is what any paginated listing needs, otherwise deliveries show
     * up on several pages and pages hold varying numbers of them.
     */
    public function firstPickupOnly(QueryBuilder $qb): QueryBuilder
    {
        return $qb
            ->andWhere('t.id = (SELECT MIN(t2.id) FROM ' . Task::class . ' t2'
                . ' JOIN ' . TaskCollectionItem::class . ' i2 WITH i2.task = t2.id'
                . ' WHERE i2.parent = d.id AND t2.type = :pickup)')
            ->setParameter('pickup', Task::TYPE_PICKUP)
            ;
    }

    public function today(QueryBuilder $qb): QueryBuilder
    {
        $today = Carbon::now();

        return $this->firstPickupOnly(clone $qb)
            ->andWhere('t.doneBefore >= :after')
            ->andWhere('t.doneAfter <= :before')
            ->setParameter('after', $today->clone()->startOfDay())
            ->setParameter('before', $today->clone()->endOfDay())
            ;
    }

    public function upcoming(QueryBuilder $qb): QueryBuilder
    {
        $today = Carbon::now();

        return $this->firstPickupOnly(clone $qb)
            ->andWhere('t.doneAfter > :endOfToday')
            ->setParameter('endOfToday', $today->clone()->endOfDay())
            ->orderBy('t.doneBefore', 'asc')
            ;
    }

    public function past(QueryBuilder $qb): QueryBuilder
    {
        $today = Carbon::now();

        return $this->firstPickupOnly(clone $qb)
            ->andWhere('t.doneBefore < :startOfToday')
            ->setParameter('startOfToday', $today->clone()->startOfDay())
            ;
    }
    /**
     * Filters on the date the delivery happens, *not* on the date it was
     * created: the listings display & sort t.doneBefore (see today(), past() &
     * upcoming()), so filtering on d.createdAt made deliveries scheduled
     * outside the selected range show up.
     *
     * Callers are expected to have restricted the join to a single task
     * (firstPickupOnly()), otherwise a delivery matches as soon as any of its
     * tasks falls in the range, and is listed once per matching task.
     */
    public function dateRange(QueryBuilder $qb, \DateTimeInterface $start, \DateTimeInterface $end): QueryBuilder
    {
        return $qb
            ->andWhere('t.doneBefore BETWEEN :start AND :end')
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

        // New deliveries store provenance on the delivery metadata bag
        // (delivery.metadata->'rdc'->>'lo_uri'); deliveries imported before that
        // refactor kept it on the pickup task metadata (task.metadata->>'rdc_lo_uri').
        $sql = "SELECT DISTINCT d.id FROM delivery d
            JOIN task_collection_item tci ON tci.parent = d.id
            JOIN task t ON tci.task_id = t.id
            WHERE d.metadata->'rdc'->>'lo_uri' = :loUri
               OR t.metadata->>'rdc_lo_uri' = :loUri";

        $result = $conn->executeQuery($sql, ['lo_uri' => $loUri])->fetchOne();

        if ($result === false) {
            return null;
        }

        return $this->find($result);
    }

}
