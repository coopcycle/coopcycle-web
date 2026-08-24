<?php

namespace Tests\AppBundle\Service;

use AppBundle\Service\BankHolidayProvider;
use PHPUnit\Framework\TestCase;

class BankHolidayProviderTest extends TestCase
{
    public function testFindsHolidayInRange()
    {
        $provider = new BankHolidayProvider('fr');

        $holidays = $provider->getHolidaysBetween(
            new \DateTime('2026-07-13'),
            new \DateTime('2026-07-19'),
            'fr'
        );

        $this->assertCount(1, $holidays);
        $this->assertSame('2026-07-14', $holidays[0]['date']);
        $this->assertNotEmpty($holidays[0]['name']);
    }

    public function testReturnsEmptyArrayWhenNoHolidayInRange()
    {
        $provider = new BankHolidayProvider('fr');

        $holidays = $provider->getHolidaysBetween(
            new \DateTime('2026-08-03'),
            new \DateTime('2026-08-09'),
            'fr'
        );

        $this->assertSame([], $holidays);
    }

    public function testHandlesRangeSpanningTwoYears()
    {
        $provider = new BankHolidayProvider('fr');

        // Week around New Year's Day
        $holidays = $provider->getHolidaysBetween(
            new \DateTime('2026-12-28'),
            new \DateTime('2027-01-03'),
            'fr'
        );

        $dates = array_column($holidays, 'date');
        $this->assertContains('2027-01-01', $dates);
    }

    public function testUnsupportedCountryReturnsEmpty()
    {
        $provider = new BankHolidayProvider('xx');

        $this->assertFalse($provider->isSupported());
        $this->assertSame(
            [],
            $provider->getHolidaysBetween(new \DateTime('2026-07-13'), new \DateTime('2026-07-19'))
        );
    }

    public function testSupportedCountryReportsSupported()
    {
        $provider = new BankHolidayProvider('fr');

        $this->assertTrue($provider->isSupported());
    }
}
