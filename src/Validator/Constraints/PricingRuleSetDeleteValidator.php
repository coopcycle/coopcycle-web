<?php

namespace AppBundle\Validator\Constraints;


use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Serializer\PricingRuleSetApplicationsNormalizer;
use AppBundle\Service\PricingRuleSetManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Contracts\Translation\TranslatorInterface;

class PricingRuleSetDeleteValidator extends ConstraintValidator
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected TranslatorInterface $translatorInterface,
        protected PricingRuleSetManager $pricingRuleSetManager,
        protected PricingRuleSetApplicationsNormalizer $normalizer
    ) {}

    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof PricingRuleSet) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', PricingRuleSet::class));
        }

        // don't take into account softdeleted stores
        $this->entityManager->getFilters()->enable('soft_deleteable');
        $relatedEntities = $this->pricingRuleSetManager->getPricingRuleSetApplications($object);
        $this->entityManager->getFilters()->disable('soft_deleteable');


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
