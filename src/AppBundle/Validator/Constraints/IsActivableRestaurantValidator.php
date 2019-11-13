<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Restaurant;
use AppBundle\Service\SettingsManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Validation;


class IsActivableRestaurantValidator extends ConstraintValidator
{
    private $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    public function validate($object, Constraint $constraint)
    {
        $validator = Validation::createValidator();

        $nameErrors = $validator->validate($object->getName(), new Assert\NotBlank());
        if (count($nameErrors) > 0) {
            $this->context->buildViolation($constraint->nameMessage)
                ->atPath('name')
                ->addViolation();
        }

        if ($object->getState() === 'pledge') {
            return;
        }

        $telephoneErrors = $validator->validate($object->getTelephone(), new Assert\NotBlank());
        if (count($telephoneErrors) > 0) {
            $this->context->buildViolation($constraint->telephoneMessage)
                ->atPath('telephone')
                ->addViolation();
        }

        $openingHoursErrors = $validator->validate($object->getOpeningHours(), new Assert\NotBlank());
        if (count($openingHoursErrors) > 0) {
            $this->context->buildViolation($constraint->openingHoursMessage)
                ->atPath('openingHours')
                ->addViolation();
        }

        $contractErrors = $validator->validate($object->getContract(), [
            new Assert\NotBlank(),
            new Assert\Valid(),
        ]);
        if (count($contractErrors) > 0) {
            $this->context->buildViolation($constraint->contractMessage)
                ->atPath('contract')
                ->addViolation();
        }

        // The validations below only make sense when the restaurant is created
        if (null !== $object->getId()) {

            if (!$object->hasMenu()) {
                $this->context->buildViolation($constraint->menuMessage)
                    ->atPath('activeMenuTaxon')
                    ->addViolation();
            }

            $stripeAccount = $object->getStripeAccount($this->settingsManager->isStripeLivemode());
            if (null === $stripeAccount) {
                $this->context->buildViolation($constraint->stripeAccountMessage)
                    ->atPath('stripeAccounts')
                    ->addViolation();
            }
        }

        $hasErrors = count($this->context->getViolations()) > 0;

        if ($hasErrors) {
            $this->context->buildViolation($constraint->enabledMessage)
                ->atPath('enabled')
                ->addViolation();
        }
    }
}
