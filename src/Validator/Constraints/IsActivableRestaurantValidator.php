<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusiness\FulfillmentMethod;
use AppBundle\Service\SettingsManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Validation;
use AppBundle\Payment\GatewayResolver;

class IsActivableRestaurantValidator extends ConstraintValidator
{
    private $settingsManager;
    private $resolver;

    public function __construct(SettingsManager $settingsManager, GatewayResolver $resolver)
    {
        $this->settingsManager = $settingsManager;
        $this->resolver = $resolver;
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

        if ($object->getState() === LocalBusiness::STATE_PLEDGE) {
            return;
        }

        $telephoneErrors = $validator->validate($object->getTelephone(), new Assert\NotBlank());
        if (count($telephoneErrors) > 0) {
            $this->context->buildViolation($constraint->telephoneMessage)
                ->atPath('telephone')
                ->addViolation();
        }

        foreach ($object->getFulfillmentMethods() as $index => $fulfillmentMethod) {
            if ($fulfillmentMethod->isEnabled()) {
                $openingHoursErrors = $validator->validate(
                    $fulfillmentMethod->getOpeningHours(),
                    new Assert\NotBlank()
                );
                if (count($openingHoursErrors) > 0) {
                    $this->context->buildViolation($constraint->openingHoursMessage)
                        ->atPath(sprintf('fulfillmentMethods[%d].openingHours', $index))
                        ->addViolation();
                }
            }
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

            if ('mercadopago' === $this->resolver->resolve()) {
                $mercadopagoAccount = $object->getMercadopagoAccount($this->settingsManager->isMercadopagoLivemode());
                if (null === $mercadopagoAccount) {
                    $this->context->buildViolation($constraint->mercadopagoAccountMessage)
                        ->atPath('mercadopagoAccounts')
                        ->addViolation();
                }
            } else {
                $stripeAccount = $object->getStripeAccount($this->settingsManager->isStripeLivemode());
                if (null === $stripeAccount) {
                    $this->context->buildViolation($constraint->stripeAccountMessage)
                        ->atPath('stripeAccounts')
                        ->addViolation();
                }
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
