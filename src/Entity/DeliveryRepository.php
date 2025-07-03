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
            ->andWhere('t.doneAfter >= :after')
            ->andWhere('t.doneBefore <= :before')
            ->setParameter('pickup', Task::TYPE_PICKUP)
            ->setParameter('after', $today->copy()->hour(0)->minute(0)->second(0))
            ->setParameter('before', $today->copy()->hour(23)->minute(59)->second(59));
    }

    public function upcoming(QueryBuilder $qb): QueryBuilder
    {
        $today = Carbon::now();

        return (clone $qb)
            ->andWhere('t.type = :pickup')
            ->andWhere('t.doneAfter >= :after')
            ->setParameter('pickup', Task::TYPE_PICKUP)
            ->setParameter('after', $today->copy()->add(1, 'day')->hour(0)->minute(0)->second(0))
            ->orderBy('t.doneBefore', 'asc')
            ;
    }

    public function past(QueryBuilder $qb): QueryBuilder
    {
        $today = Carbon::now();

        return (clone $qb)
            ->andWhere('t.type = :pickup')
            ->andWhere('t.doneBefore < :after')
            ->setParameter('pickup', Task::TYPE_PICKUP)
            ->setParameter('after', $today->copy()->sub(1, 'day')->hour(23)->minute(59)->second(59))
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
    public function findDeliveriesByStore(Store|int $store, \DateTimeInterface $dateA, \DateTimeInterface $dateB)
    {
        $qb = $this->createQueryBuilderWithTasks()
            ->leftJoin('t.images', 'p');

        return $qb->andWhere('d.store = :store')
            ->andWhere('t.type = :dropoff')
            ->andWhere('t.doneAfter >= :dateA')
            ->andWhere('t.doneBefore <= :dateB')
            ->setParameter('dropoff', Task::TYPE_DROPOFF)
            ->setParameter('store', $store)
            ->setParameter('dateA', $dateA)
            ->setParameter('dateB', $dateB)
            ->getQuery()
            ->getResult();
    }

}
