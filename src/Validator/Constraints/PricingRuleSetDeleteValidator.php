<?php

namespace AppBundle\Validator\Constraints;


use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Serializer\ApplicationsNormalizer;
use AppBundle\Service\PricingRuleSetManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;


class PricingRuleSetDeleteValidator extends ConstraintValidator
{
    public function __construct(
        protected PricingRuleSetManager $pricingRuleSetManager,
        protected ApplicationsNormalizer $normalizer
    ) {}

    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof PricingRuleSet) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', PricingRuleSet::class));
        }

        $relatedEntities = $this->pricingRuleSetManager->getPricingRuleSetApplications($object);

        if (count($relatedEntities) > 0) {
            $this->context
                ->buildViolation(
                    json_encode(array_map(
                        function ($entity) {return $this->normalizer->normalize($entity);},
                        $relatedEntities
                    ))
                )
                ->atPath('error')
                ->addViolation();
        }
    }
}
