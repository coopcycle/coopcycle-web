<?php

declare(strict_types=1);

namespace Tests\AppBundle\Validator\Constraints;

use AppBundle\Validator\Constraints\RecurrenceRuleTemplate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RecurrenceRuleTemplateValidatorTest extends TestCase
{
    private ValidatorInterface $validator;

    public function setUp(): void
    {
        $this->validator = Validation::createValidator();
    }

    public function testTimeWithoutSecondsIsValid(): void
    {
        $violations = $this->validator->validate([
            '@type' => 'Task',
            'after' => '11:30',
            'before' => '12:00',
        ], new RecurrenceRuleTemplate());

        $this->assertCount(0, $violations);
    }

    public function testTimeWithSecondsIsValid(): void
    {
        // As saved when a recurrence rule is created from a delivery
        $violations = $this->validator->validate([
            '@type' => 'hydra:Collection',
            'hydra:member' => [
                ['after' => '12:45:00', 'before' => '13:00:00'],
                ['after' => '13:15:00', 'before' => '13:30:00'],
            ],
        ], new RecurrenceRuleTemplate());

        $this->assertCount(0, $violations);
    }

    public function testInvalidTimeIsRejected(): void
    {
        $violations = $this->validator->validate([
            '@type' => 'hydra:Collection',
            'hydra:member' => [
                ['after' => '9:5', 'before' => 'noon'],
            ],
        ], new RecurrenceRuleTemplate());

        $this->assertCount(2, $violations);
        $this->assertEquals('[hydra:member][0][after]', $violations->get(0)->getPropertyPath());
        $this->assertEquals('[hydra:member][0][before]', $violations->get(1)->getPropertyPath());
    }
}
