<?php

namespace AppBundle\Utils\SearchQuery;

use PHPUnit\Framework\TestCase;

class SearchQueryParserTest extends TestCase
{
    private SearchQueryParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SearchQueryParser();
    }

    public function testEmptyQuery()
    {
        $query = $this->parser->parse('');

        $this->assertTrue($query->isEmpty());
        $this->assertSame([], $query->getTerms());
        $this->assertNull($query->getFilter('state'));
    }

    public function testNullQuery()
    {
        $query = $this->parser->parse(null);

        $this->assertTrue($query->isEmpty());
    }

    public function testSimpleFilter()
    {
        $query = $this->parser->parse('date:2026-09-08');

        $filter = $query->getFilter('date');
        $this->assertNotNull($filter);
        $this->assertSame('2026-09-08', $filter->value);
        $this->assertFalse($filter->exclude);
        $this->assertSame([], $query->getTerms());
    }

    public function testExcludedFilter()
    {
        $query = $this->parser->parse('-state:cancelled');

        $filter = $query->getFilter('state');
        $this->assertNotNull($filter);
        $this->assertSame('cancelled', $filter->value);
        $this->assertTrue($filter->exclude);
    }

    public function testMultipleFiltersWithSameKey()
    {
        $query = $this->parser->parse('state:new state:accepted -state:cancelled');

        $filters = $query->getFilters('state');
        $this->assertCount(3, $filters);
        $this->assertSame('new', $filters[0]->value);
        $this->assertFalse($filters[0]->exclude);
        $this->assertSame('accepted', $filters[1]->value);
        $this->assertSame('cancelled', $filters[2]->value);
        $this->assertTrue($filters[2]->exclude);
    }

    public function testQuotedValueWithSpaces()
    {
        $query = $this->parser->parse('owner:"Colis prompto"');

        $filter = $query->getFilter('owner');
        $this->assertSame('Colis prompto', $filter->value);
    }

    public function testFreeTextTerms()
    {
        $query = $this->parser->parse('foo@example.com bar');

        $this->assertSame(['foo@example.com', 'bar'], $query->getTerms());
        $this->assertSame('foo@example.com bar', $query->getFullText());
    }

    public function testMixedFiltersAndFreeText()
    {
        $query = $this->parser->parse('date:2026-09-08 -state:cancelled foo@example.com "some text"');

        $this->assertSame('2026-09-08', $query->getFilter('date')->value);
        $this->assertSame('cancelled', $query->getFilter('state')->value);
        $this->assertTrue($query->getFilter('state')->exclude);
        $this->assertSame(['foo@example.com', 'some text'], $query->getTerms());
    }

    public function testLoneMinusIsATerm()
    {
        $query = $this->parser->parse('-');

        $this->assertSame(['-'], $query->getTerms());
    }

    public function testColonWithoutValueIsATerm()
    {
        $query = $this->parser->parse('date:');

        $this->assertSame(['date:'], $query->getTerms());
        $this->assertNull($query->getFilter('date'));
    }

    public function testGroupValueExpandsToMultipleFilters()
    {
        $query = $this->parser->parse('owner:("Colis prompto" OR "Couture express")');

        $filters = $query->getFilters('owner');
        $this->assertCount(2, $filters);
        $this->assertSame('Colis prompto', $filters[0]->value);
        $this->assertFalse($filters[0]->exclude);
        $this->assertSame('Couture express', $filters[1]->value);
        $this->assertFalse($filters[1]->exclude);
    }

    public function testExcludedGroupValueExpandsToMultipleExcludedFilters()
    {
        $query = $this->parser->parse('-owner:(Acme OR Bistro)');

        $filters = $query->getFilters('owner');
        $this->assertCount(2, $filters);
        $this->assertTrue($filters[0]->exclude);
        $this->assertTrue($filters[1]->exclude);
    }

    public function testGroupValueWithSingleValue()
    {
        $query = $this->parser->parse('owner:("Colis prompto")');

        $filters = $query->getFilters('owner');
        $this->assertCount(1, $filters);
        $this->assertSame('Colis prompto', $filters[0]->value);
    }

    public function testGroupValueDoesNotAffectOtherTokens()
    {
        $query = $this->parser->parse('owner:(Acme OR Bistro) state:new -state:cancelled foo');

        $this->assertCount(2, $query->getFilters('owner'));
        $this->assertCount(2, $query->getFilters('state'));
        $this->assertSame(['foo'], $query->getTerms());
    }
}
