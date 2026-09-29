<?php

namespace AppBundle\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Sylius\Payment\MealVoucherPaymentMethods;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\PropertyInfo\Type;

/**
 * Filters orders by whether CoopCycle still needs to invoice their organization
 * for them (see InvoiceLineItemAmountCalculator for the underlying rules):
 * Store orders always need invoicing; restaurant orders only when paid (fully
 * or partially) by meal voucher, since card payments are already settled
 * automatically via Stripe Connect.
 *
 * Accepted values for the "settlement" query param: "needs_invoicing" or
 * "settled". Anything else (including no value) is a no-op, same as today.
 */
final class OrderSettlementFilter extends AbstractFilter
{
    private const NEEDS_INVOICING = 'needs_invoicing';
    private const SETTLED = 'settled';

    private string $settlementAlias = 'settlement';

    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if ($resourceClass !== Order::class) {
            return;
        }

        if ($this->settlementAlias !== $property) {
            return;
        }

        if (!in_array($value, [self::NEEDS_INVOICING, self::SETTLED], true)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        $deliveryAlias = $queryNameGenerator->generateJoinAlias('delivery');
        $queryBuilder->leftJoin(sprintf('%s.delivery', $rootAlias), $deliveryAlias);
        $isStoreOrder = sprintf('%s.store IS NOT NULL', $deliveryAlias);

        $paymentAlias = $queryNameGenerator->generateJoinAlias('payment');
        $methodAlias = $queryNameGenerator->generateJoinAlias('paymentMethod');
        $paymentStateParameter = $queryNameGenerator->generateParameterName('settlementPaymentState');
        $voucherCodesParameter = $queryNameGenerator->generateParameterName('settlementVoucherCodes');

        $subQueryBuilder = $queryBuilder->getEntityManager()->createQueryBuilder();
        $subQueryBuilder
            ->select('1')
            ->from(Payment::class, $paymentAlias)
            ->innerJoin(sprintf('%s.method', $paymentAlias), $methodAlias)
            ->where(sprintf('%s.order = %s', $paymentAlias, $rootAlias))
            ->andWhere(sprintf('%s.state = :%s', $paymentAlias, $paymentStateParameter))
            ->andWhere(sprintf('%s.code IN (:%s)', $methodAlias, $voucherCodesParameter));

        $hasCompletedVoucherPayment = sprintf('EXISTS (%s)', $subQueryBuilder->getDQL());

        if (self::NEEDS_INVOICING === $value) {
            $queryBuilder->andWhere($queryBuilder->expr()->orX($isStoreOrder, $hasCompletedVoucherPayment));
        } else {
            // "settled" is meaningless for Store orders (there is no automatic
            // settlement for them), so they're deliberately excluded here
            $queryBuilder->andWhere($queryBuilder->expr()->andX(
                $queryBuilder->expr()->not($isStoreOrder),
                $queryBuilder->expr()->not($hasCompletedVoucherPayment)
            ));
        }

        $queryBuilder->setParameter($paymentStateParameter, PaymentInterface::STATE_COMPLETED);
        $queryBuilder->setParameter($voucherCodesParameter, MealVoucherPaymentMethods::CODES);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'settlement' => [
                'property' => $this->settlementAlias,
                'type' => Type::BUILTIN_TYPE_STRING,
                'required' => false,
                'is_collection' => false,
            ],
        ];
    }
}
