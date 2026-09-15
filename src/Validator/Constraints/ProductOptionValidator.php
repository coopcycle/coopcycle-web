<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ProductOptionValidator extends ConstraintValidator
{
    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof ProductOptionInterface) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', ProductOptionInterface::class));
        }

        if (!$object->isAdditional() && null !== $object->getValuesRange()) {
            $this->context->buildViolation($constraint->rangeNotAllowed)
                ->atPath('valuesRange')
                ->addViolation();
        }

        $this->validateDistinctProductLinks($object, $constraint);
    }

    /**
     * A value's `product` link is meant to be a 1:1 correspondence — "this
     * choice IS that specific dish" — it's what lets DisabledProductListener
     * disable the choice when the dish itself gets disabled. Two or more
     * distinct values pointing at the same product is never legitimate: in
     * production, six unrelated "Suppléments" choices (bacon, cheese, beef,
     * onion confit, red onion, avocado) all ended up linked to one unrelated
     * dish, which silently disabled all six the day that dish was disabled.
     */
    private function validateDistinctProductLinks(ProductOptionInterface $object, Constraint $constraint): void
    {
        $seenProductIds = [];

        foreach ($object->getValues() as $value) {
            if (!$value instanceof ProductOptionValue) {
                continue;
            }

            $product = $value->getProduct();

            if ($product === null || $product->getId() === null) {
                continue;
            }

            $productId = $product->getId();

            if (isset($seenProductIds[$productId])) {
                $this->context->buildViolation($constraint->duplicateProductLink)
                    ->atPath('values')
                    ->setParameter('%product%', (string) $product->getName())
                    ->addViolation();

                continue;
            }

            $seenProductIds[$productId] = true;
        }
    }
}
