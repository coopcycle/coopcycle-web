<?php

namespace Tests\AppBundle\OpeningHours;

use AppBundle\Entity\ClosingRule;
use AppBundle\OpeningHours\OpenCloseInterface;
use AppBundle\OpeningHours\OpenCloseTrait;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class OpenCloseTraitTest extends TestCase implements OpenCloseInterface
{
    use OpenCloseTrait;

    private $openingHours = [];
    private $closingRules;

    public function setUp(): void
    {
        $this->closingRules = new ArrayCollection();
    }

    public function getOpeningHours($method = 'delivery')
    {
        return $this->openingHours;
    }

    public function getClosingRules()
    {
        return $this->closingRules;
    }

    public function setShippingOptionsDays(int $shippingOptionsDays)
    {
        return 2;
    }

    public function getOrderingDelayMinutes()
    {
        return 0;
    }

    public function testGetNextClosingDate()
    {
        $this->openingHours = ["Mo-Sa 10:00-19:00"];

        $this->assertEquals(
            new \DateTime('2019-08-05T19:00:00+02:00'),
            $this->getNextClosingDate(new \DateTime('2019-08-05T12:00:00+02:00'))
        );
    }

    public function testGetNextClosingDateWithClosingRules()
    {
        $this->openingHours = ["Mo-Sa 10:00-19:00"];

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime('2019-08-05T12:00:00+02:00'));
        $closingRule->setEndDate(new \DateTime('2019-08-06T12:00:00+02:00'));

        $this->closingRules->add($closingRule);

        $this->assertEquals(
            new \DateTime('2019-08-05T12:00:00+02:00'),
            $this->getNextClosingDate(new \DateTime('2019-08-05T11:00:00+02:00'))
        );
    }

    public function testGetNextOpeningDateWithHolidays()
    {
        $this->openingHours = ["Mo-Sa 10:00-19:00"];

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime('2018-12-24T00:00:00+02:00'));
        $closingRule->setEndDate(new \DateTime('2019-01-01T10:00:00+02:00'));

        $this->closingRules->add($closingRule);

        $now = new \DateTime('2018-12-24T12:00:00+02:00');

        $nextOpeningDate = $this->getNextOpeningDate($now);

        $this->assertEquals(new \DateTime('2019-01-01T10:00:00+02:00'), $nextOpeningDate);
    }
}
