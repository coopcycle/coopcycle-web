<?php

namespace Tests\AppBundle\Entity\LocalBusiness;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusiness\DeliveryPerimeterExpressionResolver;
use AppBundle\Entity\LocalBusiness\DayOfWeekDeliveryPerimeterExpression;
use PHPUnit\Framework\TestCase;

class DeliveryPerimeterExpressionResolverTest extends TestCase
{
    private function makeEntry(string $daysOfWeek, string $expression): DayOfWeekDeliveryPerimeterExpression
    {
        $entry = new DayOfWeekDeliveryPerimeterExpression();
        $entry->setDaysOfWeek($daysOfWeek);
        $entry->setExpression($expression);

        return $entry;
    }

    public function testReturnsDefaultExpressionWhenNoEntriesExist(): void
    {
        $shop = new LocalBusiness();
        $shop->setDeliveryPerimeterExpression('distance < 3000');

        $this->assertSame(
            'distance < 3000',
            DeliveryPerimeterExpressionResolver::resolve($shop)
        );
    }

    public function testReturnsMatchingExpressionForDay(): void
    {
        $shop = new LocalBusiness();
        $shop->setDeliveryPerimeterExpression('distance < 3000');
        $shop->addDayOfWeekDeliveryPerimeterExpression(
            $this->makeEntry('Mo', 'distance < 1000')
        );

        $monday    = new \DateTimeImmutable('2026-03-09'); // Monday
        $tuesday   = new \DateTimeImmutable('2026-03-10');
        $wednesday = new \DateTimeImmutable('2026-03-11');

        $this->assertSame('distance < 1000',
            DeliveryPerimeterExpressionResolver::resolve($shop, $monday));
        $this->assertSame('distance < 3000',
            DeliveryPerimeterExpressionResolver::resolve($shop, $tuesday));
        $this->assertSame('distance < 3000',
            DeliveryPerimeterExpressionResolver::resolve($shop, $wednesday));
    }

    public function testReturnsMatchingExpressionForMultipleDays(): void
    {
        $shop = new LocalBusiness();
        $shop->setDeliveryPerimeterExpression('distance < 3000');
        $shop->addDayOfWeekDeliveryPerimeterExpression(
            $this->makeEntry('Mo,Tu', 'distance < 5000')
        );

        $monday    = new \DateTimeImmutable('2026-03-09');
        $tuesday   = new \DateTimeImmutable('2026-03-10');
        $wednesday = new \DateTimeImmutable('2026-03-11');

        $this->assertSame('distance < 5000',
            DeliveryPerimeterExpressionResolver::resolve($shop, $monday));
        $this->assertSame('distance < 5000',
            DeliveryPerimeterExpressionResolver::resolve($shop, $tuesday));
        $this->assertSame('distance < 3000',
            DeliveryPerimeterExpressionResolver::resolve($shop, $wednesday));
    }

    public function testReturnsDefaultWhenNoDayMatches(): void
    {
        $shop = new LocalBusiness();
        $shop->setDeliveryPerimeterExpression('distance < 3000');
        $shop->addDayOfWeekDeliveryPerimeterExpression(
            $this->makeEntry('Sa,Su', 'distance < 500')
        );

        $monday = new \DateTimeImmutable('2026-03-09');

        $this->assertSame('distance < 3000',
            DeliveryPerimeterExpressionResolver::resolve($shop, $monday));
    }

    public function testFirstMatchingEntryWins(): void
    {
        $shop = new LocalBusiness();
        $shop->setDeliveryPerimeterExpression('distance < 3000');
        $shop->addDayOfWeekDeliveryPerimeterExpression(
            $this->makeEntry('Mo', 'distance < 1000')
        );
        $shop->addDayOfWeekDeliveryPerimeterExpression(
            $this->makeEntry('Mo', 'distance < 9000')
        );

        $monday = new \DateTimeImmutable('2026-03-09');

        $this->assertSame('distance < 1000',
            DeliveryPerimeterExpressionResolver::resolve($shop, $monday));
    }

    public function testSupportsDifferentExpressionsPerDayGroup(): void
    {
        $shop = new LocalBusiness();
        $shop->setDeliveryPerimeterExpression('distance < 3000');
        $shop->addDayOfWeekDeliveryPerimeterExpression(
            $this->makeEntry('Mo,Tu,We,Th,Fr', 'distance < 2000')
        );
        $shop->addDayOfWeekDeliveryPerimeterExpression(
            $this->makeEntry('Sa,Su', 'distance < 5000')
        );

        $friday   = new \DateTimeImmutable('2026-03-13');
        $saturday = new \DateTimeImmutable('2026-03-14');

        $this->assertSame('distance < 2000',
            DeliveryPerimeterExpressionResolver::resolve($shop, $friday));
        $this->assertSame('distance < 5000',
            DeliveryPerimeterExpressionResolver::resolve($shop, $saturday));
    }
}
