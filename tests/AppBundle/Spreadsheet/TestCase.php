<?php

namespace Tests\AppBundle\Spreadsheet;

use AppBundle\Spreadsheet\AbstractSpreadsheetParser;
use Cocur\Slugify\SlugifyInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class TestCase extends KernelTestCase
{
    use ProphecyTrait;

    protected $parser;
    protected $geocoder;
    protected $packageRepository;
    protected $entityManager;
    protected $slugify;
    protected $translator;

    public function setUp(): void
    {
        self::bootKernel();
        $this->translator = self::getContainer()->get(TranslatorInterface::class);
        $this->parser = $this->createParser();
    }

    abstract protected function createParser(): AbstractSpreadsheetParser;

    public function testCanParseExampleData()
    {
        $results = $this->parser->parseData($this->parser->getExampleData());

        $this->assertNotEmpty($results);
    }
}
