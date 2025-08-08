<?php

namespace AppBundle\Validator\Constraints;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Api\Dto\DeliveryDto;
use AppBundle\Api\Dto\DeliveryOrderDto;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Store;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ManualSupplementsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly IriConverterInterface $iriConverter
    ) {
    }

    /**
     * @param DeliveryOrderDto $value
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$value instanceof DeliveryOrderDto) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', DeliveryOrderDto::class));
        }

        if (empty($value->manualSupplements)) {
            return;
        }

        // Get the store from the parent DeliveryDto object
        $rootObject = $this->context->getRoot();
        $store = null;

        if ($rootObject instanceof DeliveryDto && null !== $rootObject->store) {
            $store = $rootObject->store;
        }

        if (!$store instanceof Store) {
            return; // Cannot validate without a store
        }

        $pricingRuleSet = $store->getPricingRuleSet();
        if (null === $pricingRuleSet) {
            // If store has no pricing rule set, no manual supplements are allowed
            $this->context
                ->buildViolation($constraint->supplementNotInStoreRuleSetMessage)
                ->atPath('manualSupplements')
                ->addViolation();
            return;
        }



        foreach ($value->manualSupplements as $index => $supplement) {
            if (null === $supplement->pricingRule) {
                $this->context
                    ->buildViolation($constraint->invalidSupplementMessage)
                    ->atPath("manualSupplements[{$index}][@id]")
                    ->addViolation();
                continue;
            }

            // Check if the supplement PricingRule belongs to the store's pricing rule set
            if (!$pricingRuleSet->getRules()->contains($supplement->pricingRule)) {
                $supplementIri = $this->iriConverter->getIriFromResource($supplement->pricingRule);
                $this->context
                    ->buildViolation($constraint->supplementNotInStoreRuleSetMessage)
                    ->setParameter('%supplement_uri%', $supplementIri)
                    ->atPath("manualSupplements[{$index}][@id]")
                    ->addViolation();
            }
        }
    }
}
