<?php

namespace AppBundle\Service;

use Yasumi\Yasumi;

/**
 * Thin wrapper around Yasumi to look up public/bank holidays for the coop's
 * configured country, for a given date range. Used to visually highlight
 * bank holidays in the shift planning grid (informational only, never blocks).
 */
class BankHolidayProvider
{
    private ?string $providerClass;

    public function __construct(string $country)
    {
        $providers = Yasumi::getProviders();

        $this->providerClass = $providers[strtoupper($country)] ?? null;
    }

    public function isSupported(): bool
    {
        return null !== $this->providerClass;
    }

    /**
     * @return array<int, array{date: string, name: string}> sorted by date
     */
    public function getHolidaysBetween(\DateTimeInterface $start, \DateTimeInterface $end, string $locale = 'en'): array
    {
        if (null === $this->providerClass) {
            return [];
        }

        $startYear = (int) $start->format('Y');
        $endYear = (int) $end->format('Y');

        // Keyed by date to naturally de-duplicate (a holiday can't repeat within a range)
        $holidays = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            try {
                $provider = Yasumi::create($this->providerClass, $year);
            } catch (\Exception $e) {
                // Unsupported year for this provider (out of Yasumi's bounds)
                continue;
            }

            foreach ($provider->between($start, $end, true) as $holiday) {
                $date = $holiday->format('Y-m-d');
                $holidays[$date] = [
                    'date' => $date,
                    'name' => $holiday->getName([$locale, 'en_US']),
                ];
            }
        }

        ksort($holidays);

        return array_values($holidays);
    }
}
