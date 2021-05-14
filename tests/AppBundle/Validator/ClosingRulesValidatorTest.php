<?php

namespace AppBundle\Validator;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\ClosingRule;
use AppBundle\Validator\Constraints\ClosingRules as ClosingRulesConstraint;
use AppBundle\Validator\Constraints\ClosingRulesValidator;
use Carbon\Carbon;
use Doctrine\Common\Collections\ArrayCollection;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class ClosingRulesValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    protected function createValidator()
    {
        return new ClosingRulesValidator();
    }

    public function closingRulesProvider()
    {
        return [
            [
                new ArrayCollection([
                    ClosingRule::create('2020-12-24T12:00:00+01:00', '2020-12-28T09:00:00+01:00')
                ]),
                ['2020-12-24T09:00:00+01:00', '2020-12-24T10:00:00+01:00'],
                true,
            ],
            [
                new ArrayCollection([
                    ClosingRule::create('2020-12-24T12:00:00+01:00', '2020-12-28T09:00:00+01:00'),
                ]),
                ['2020-12-24T09:30:00+01:00', '2020-12-24T12:30:00+01:00'],
                false,
            ],
            [
                new ArrayCollection([
                    ClosingRule::create('2020-12-24T12:00:00+01:00', '2020-12-28T09:00:00+01:00'),
                ]),
                ['2020-12-24T12:30:00+01:00', '2020-12-24T13:30:00+01:00'],
                false,
            ],
            [
                new ArrayCollection([
                    ClosingRule::create('2020-12-24T12:00:00+01:00', '2020-12-28T09:00:00+01:00'),
                ]),
                ['2020-12-25T09:30:00+01:00', '2020-12-25T11:30:00+01:00'],
                false,
            ],
            [
                new ArrayCollection([
                    ClosingRule::create('2020-12-24T12:00:00+01:00', '2020-12-28T09:00:00+01:00'),
                ]),
                ['2020-12-28T08:30:00+01:00', '2020-12-28T10:30:00+01:00'],
                false,
            ],
            [
                new ArrayCollection([
                    ClosingRule::create('2020-12-24T12:00:00+01:00', '2020-12-28T09:00:00+01:00'),
                ]),
                ['2020-12-28T09:30:00+01:00', '2020-12-28T10:30:00+01:00'],
                true,
            ],
            [
                new ArrayCollection([
                    ClosingRule::create('2020-12-24T12:00:00+01:00', '2020-12-28T09:00:00+01:00'),
                ]),
                ['2020-12-28T09:00:00+01:00', '2020-12-28T11:30:00+01:00'],
                true,
            ],
            [
                new ArrayCollection([
                    ClosingRule::create('2020-12-24T12:00:00+01:00', '2020-12-28T09:00:00+01:00'),
                ]),
                ['2020-12-29T09:30:00+01:00', '2020-12-29T11:30:00+01:00'],
                true,
            ],
            [
                new ArrayCollection([
                    ClosingRule::create('2019-12-24T12:00:00+01:00', '2019-12-28T09:00:00+01:00'),
                    ClosingRule::create('2020-12-24T12:00:00+01:00', '2020-12-28T09:00:00+01:00'),
                ]),
                ['2020-12-29T09:30:00+01:00', '2020-12-29T11:30:00+01:00'],
                true,
            ]
        ];
    }

    /**
     * @dataProvider closingRulesProvider
     */
    public function testClosingRules(ArrayCollection $closingRules, $timeRange, $isValid)
    {
        $shippingTimeRange = new TsRange();
        $shippingTimeRange->setLower(new \DateTime($timeRange[0]));
        $shippingTimeRange->setUpper(new \DateTime($timeRange[1]));

        $constraint = new ClosingRulesConstraint($closingRules);
        $this->validator->validate($shippingTimeRange, $constraint);

        if ($isValid) {
            $this->assertNoViolation();
        } else {
            $this->buildViolation($constraint->message)
                ->assertRaised();
        }
    }
}
