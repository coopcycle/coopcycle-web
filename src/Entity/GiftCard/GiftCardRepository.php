<?php

namespace AppBundle\Entity\GiftCard;

use AppBundle\Entity\Sylius\Customer;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class GiftCardRepository extends EntityRepository
{
    /**
     * Codes are dictated down the phone and typed off a printed card, so a
     * lookup has to forgive case and the spaces people add to group the
     * characters.
     */
    public function findOneByCode(string $code): ?GiftCard
    {
        return $this->createQueryBuilder('g')
            ->andWhere('UPPER(g.code) = :code')
            ->setParameter('code', self::normalizeCode($code))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    /**
     * Re-reads the card with a write lock for the duration of the
     * transaction.
     *
     * This is what stops two checkouts racing a single card. A @Version
     * column would not do: the balance is derived from a collection of
     * ledger rows, so two transactions can each append a row without ever
     * touching the same version, and both succeed.
     */
    public function findOneByIdForUpdate(int $id): ?GiftCard
    {
        return $this->find($id, LockMode::PESSIMISTIC_WRITE);
    }

    /**
     * Cards the customer can see: the ones they bought, and the ones
     * addressed to them. Matching on email as well is what keeps a guest
     * purchase visible once the buyer registers.
     */
    public function createForCustomerQueryBuilder(Customer $customer): QueryBuilder
    {
        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.state != :pending')
            ->setParameter('pending', GiftCard::STATE_PENDING)
            ->orderBy('g.createdAt', 'DESC');

        $email = $customer->getEmail();

        if (null === $email) {
            return $qb
                ->andWhere('g.purchaser = :customer')
                ->setParameter('customer', $customer);
        }

        return $qb
            ->andWhere($qb->expr()->orX(
                'g.purchaser = :customer',
                'LOWER(g.purchaserEmail) = :email',
                'LOWER(g.recipientEmail) = :email'
            ))
            ->setParameter('customer', $customer)
            ->setParameter('email', strtolower($email));
    }

    /**
     * Cards whose expiry date has passed but whose balance hasn't been
     * forfeited yet. Drives the expiry sweep.
     */
    public function createLapsedQueryBuilder(?\DateTime $now = null): QueryBuilder
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.state = :active')
            ->andWhere('g.balance > 0')
            ->andWhere('g.expiresAt IS NOT NULL')
            ->andWhere('g.expiresAt <= :now')
            ->setParameter('active', GiftCard::STATE_ACTIVE)
            ->setParameter('now', $now ?? new \DateTime())
            ->orderBy('g.expiresAt', 'ASC');
    }

    public function findOneByPurchasePaymentIntentId(string $paymentIntentId): ?GiftCard
    {
        return $this->findOneBy(['purchasePaymentIntentId' => $paymentIntentId]);
    }

    public function findOneByPurchaseChargeId(string $chargeId): ?GiftCard
    {
        return $this->findOneBy(['purchaseChargeId' => $chargeId]);
    }

    /**
     * What the coop still owes the people holding its cards. Under
     * multi-purpose voucher treatment this is a balance-sheet liability, not
     * turnover, and it is a period-end number someone will be asked for.
     */
    public function getOutstandingLiability(): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('SUM(g.balance)')
            ->andWhere('g.state IN (:states)')
            ->setParameter('states', [GiftCard::STATE_ACTIVE, GiftCard::STATE_FROZEN])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function createAdminListQueryBuilder(?string $query = null, ?string $state = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('g')
            ->orderBy('g.createdAt', 'DESC');

        if (!empty($query)) {
            $qb
                ->andWhere($qb->expr()->orX(
                    'UPPER(g.code) LIKE :query',
                    'LOWER(g.recipientEmail) LIKE :lowerQuery',
                    'LOWER(g.purchaserEmail) LIKE :lowerQuery'
                ))
                ->setParameter('query', '%'.self::normalizeCode($query).'%')
                ->setParameter('lowerQuery', '%'.strtolower(trim($query)).'%');
        }

        if (!empty($state)) {
            $qb
                ->andWhere('g.state = :state')
                ->setParameter('state', $state);
        }

        return $qb;
    }
}
