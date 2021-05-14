<?php

namespace Tests\AppBundle\Spreadsheet;

use AppBundle\Spreadsheet\AbstractSpreadsheetParser;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

abstract class TestCase extends BaseTestCase
{
    use ProphecyTrait;

    protected $parser;

    public function setUp(): void
    {
        $this->parser = $this->createParser();
    }

    abstract protected function createParser(): AbstractSpreadsheetParser;

    public function testCanParseExampleData()
    {
        $results = $this->parser->parseData($this->parser->getExampleData());

        $this->assertNotEmpty($results);
    }
}
