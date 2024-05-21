<?php

namespace AppBundle\Validator\Constraints;


use AppBundle\Entity\PackageSet;
use AppBundle\Serializer\ApplicationsNormalizer;
use AppBundle\Service\PackageSetManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;


class PackageSetDeleteValidator extends ConstraintValidator
{
    public function __construct(
        protected PackageSetManager $packageSetManager,
        protected ApplicationsNormalizer $normalizer
    ) {}

    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof PackageSet) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', PackageSet::class));
        }

        $relatedEntities = $this->packageSetManager->getPackageSetApplications($object);

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
