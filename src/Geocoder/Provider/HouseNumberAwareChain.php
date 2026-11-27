<?php

declare(strict_types=1);

namespace AppBundle\Geocoder\Provider;

use Geocoder\Collection;
use Geocoder\Model\AddressCollection;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Like the upstream chain provider (Geocoder\Provider\Chain\Chain, which is final),
 * except it doesn't stop at a result without a house number when the query has one.
 *
 * The BAN (addok) lacks a lot of house numbers, and then falls back to the street:
 * the number is lost and the position is the middle of the street.
 * The next providers get a chance to find the number; if none does,
 * the first street-level result is returned.
 */
class HouseNumberAwareChain implements Provider, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @param Provider[] $providers
     */
    public function __construct(
        private array $providers = [],
    ) {
    }

    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        $hasHouseNumber = 1 === preg_match('/^\s*\d+/', $query->getText());

        $fallback = null;
        // A loop, not array_map: stop asking providers once one has the number,
        // the next ones may be paid
        foreach ($this->providers as $provider) {
            try {
                $result = $provider->geocodeQuery($query);
            } catch (\Throwable $e) {
                $this->logger?->warning(
                    sprintf('Provider "%s" could not geocode address "%s": %s', $provider->getName(), $query->getText(), $e->getMessage()),
                    ['provider' => $provider->getName(), 'exception' => $e]
                );
                continue;
            }

            if ($result->isEmpty()) {
                continue;
            }

            if (!$hasHouseNumber || !empty($result->first()->getStreetNumber())) {
                return $result;
            }

            $this->logger?->info(
                sprintf('Provider "%s" found no house number for address "%s"', $provider->getName(), $query->getText()),
                ['provider' => $provider->getName()]
            );
            $fallback ??= $result;
        }

        return $fallback ?? new AddressCollection();
    }

    public function reverseQuery(ReverseQuery $query): Collection
    {
        $coordinates = $query->getCoordinates();

        foreach ($this->providers as $provider) {
            try {
                $result = $provider->reverseQuery($query);
            } catch (\Throwable $e) {
                $this->logger?->warning(
                    sprintf('Provider "%s" could not reverse geocode coordinates %F,%F: %s', $provider->getName(), $coordinates->getLatitude(), $coordinates->getLongitude(), $e->getMessage()),
                    ['provider' => $provider->getName(), 'exception' => $e]
                );
                continue;
            }

            if (!$result->isEmpty()) {
                return $result;
            }
        }

        return new AddressCollection();
    }

    public function getName(): string
    {
        return 'chain';
    }
}
