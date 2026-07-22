<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Action\Base;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Shared parameter handling for the proofs of delivery endpoints
 * (export & count), which both expect a store and a date range.
 */
abstract class PODAction extends Base
{
    protected const MAX_DATE_RANGE_DAYS = 7;

    /**
     * The store is always taken from the URL (/stores/{id}/…), which is also
     * what the security check is performed against.
     */
    protected function getStoreId(Request $request): int
    {
        return (int) $request->attributes->get('id');
    }

    protected function validateRequiredParameters(InputBag $params): void
    {
        $requiredParams = ['from', 'to'];
        $missingParams = array_filter(
            $requiredParams,
            fn($param) => empty($params->get($param))
        );

        if (!empty($missingParams)) {
            throw new BadRequestHttpException(
                sprintf('Missing required parameters: %s', implode(', ', $missingParams))
            );
        }
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    protected function parseDateRange(InputBag $params): array
    {
        try {
            $from = new \DateTimeImmutable($params->get('from'));
            $to = new \DateTimeImmutable($params->get('to'));
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Invalid date format. Expected format: Y-m-d or Y-m-d H:i:s');
        }

        if ($from > $to) {
            throw new BadRequestHttpException('Start date must be before or equal to end date');
        }

        $daysDiff = $to->diff($from)->days;
        if ($daysDiff > static::MAX_DATE_RANGE_DAYS) {
            throw new BadRequestHttpException(
                sprintf('Date range cannot exceed %d days', static::MAX_DATE_RANGE_DAYS)
            );
        }

        return [$from, $to];
    }
}
