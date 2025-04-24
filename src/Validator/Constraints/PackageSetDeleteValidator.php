<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\PackageSet;
use AppBundle\Service\PackageSetManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class PackageSetDeleteValidator extends ConstraintValidator
{
    public function __construct(protected PackageSetManager $packageSetManager)
    {}

    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof PackageSet) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', PackageSet::class));
        }

        $relatedEntities = $this->packageSetManager->getPackageSetApplications($object);

        if (count($relatedEntities) > 0) {
            foreach ($relatedEntities as $entity) {
                $this->context
                    ->buildViolation(
                        sprintf('%s is used by %s#%d', get_class($object), get_class($entity), $entity->getId())
                    )
                    ->addViolation();
            }
        }
    }
}
