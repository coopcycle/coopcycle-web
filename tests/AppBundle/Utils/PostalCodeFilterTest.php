<?php

declare(strict_types=1);

namespace Tests\AppBundle\Utils;

use AppBundle\Utils\PostalCodeFilter;
use PHPUnit\Framework\TestCase;

class PostalCodeFilterTest extends TestCase
{
    public function testWithoutPrefixesItIsDisabled(): void
    {
        $filter = new PostalCodeFilter();

        $this->assertFalse($filter->isEnabled());
        $this->assertFalse($filter->matches('75011'));
    }

    public function testEmptyPrefixesAreIgnored(): void
    {
        // An empty prefix would match every postal code
        $filter = new PostalCodeFilter(['', '  ']);

        $this->assertFalse($filter->isEnabled());
        $this->assertSame([], $filter->getPrefixes());
    }

    public function testPrefixesAreStrings(): void
    {
        // JSON config may hold numbers
        $filter = new PostalCodeFilter([13, ' 75011 ']);

        $this->assertSame(['13', '75011'], $filter->getPrefixes());
    }

    public function testAPostalCodeMatchesWhenItStartsWithAPrefix(): void
    {
        $filter = new PostalCodeFilter(['13', '75011']);

        $this->assertTrue($filter->matches('13001'));
        $this->assertTrue($filter->matches('75011'));
        $this->assertFalse($filter->matches('75012'));
        $this->assertFalse($filter->matches('31300'));
    }

    public function testOneMatchingPostalCodeIsEnough(): void
    {
        $filter = new PostalCodeFilter(['75']);

        $this->assertTrue($filter->matches('41100', '75015'));
        $this->assertTrue($filter->matches('75015', '41100'));
        $this->assertFalse($filter->matches('41100', '69200'));
    }

    public function testEmptyPostalCodesNeverMatch(): void
    {
        $filter = new PostalCodeFilter(['75']);

        $this->assertFalse($filter->matches(null, '', '  '));
        $this->assertFalse($filter->matches());
    }
}
