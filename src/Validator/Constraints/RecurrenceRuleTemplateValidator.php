<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;

class RecurrenceRuleTemplateValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        $context = $this->context;

        $validator = $context->getValidator()->inContext($context);

        $validator->validate($value, new Assert\Collection([
            'fields' => [
                '@type' => new Assert\Required([
                    new Assert\Choice([ 'Task', 'hydra:Collection' ])
                ]),
            ],
            'allowExtraFields' => true,
        ]));

        $violations = $context->getViolations();

        if (count($violations) === 0) {

            // A template saved from a delivery keeps the seconds (i.e. 12:45:00),
            // the form sends the time without them (i.e. 12:45)
            $taskConstraint = new Assert\Collection([
                'fields' => [
                    'after' => new Assert\Required([
                        new Assert\Regex('/^[0-9]{2}:[0-9]{2}(:[0-9]{2})?$/')
                    ]),
                    'before' => new Assert\Required([
                        new Assert\Regex('/^[0-9]{2}:[0-9]{2}(:[0-9]{2})?$/')
                    ]),
                ],
                'allowExtraFields' => true,
            ]);

            if ($value['@type'] === 'Task') {
                $validator->validate($value, $taskConstraint);
            } else {
                $validator->validate($value, new Assert\Collection([
                    'fields' => [
                        'hydra:member' => new Assert\Required([
                            new Assert\All([ $taskConstraint ])
                        ]),
                    ],
                    'allowExtraFields' => true,
                ]));
            }

        }
    }
}
