<?php

namespace Tests\AppBundle\Validator\Constraints;

use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Validator\Constraints\ProductOption as ProductOptionConstraint;
use AppBundle\Validator\Constraints\ProductOptionValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Locks in the guard against the production incident: six unrelated
 * "Suppléments" choices (bacon, cheese, beef, onion confit, red onion,
 * avocado) all ended up linked to the same single product ("Burger
 * enfant"). DisabledProductListener disabled all six the day that one
 * product was disabled — collateral damage from bad data, since a value's
 * `product` link is meant to be a 1:1 correspondence.
 */
class ProductOptionValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ProductOptionValidator
    {
        return new ProductOptionValidator();
    }

    public function testDistinctValuesLinkedToTheSameProductAreRejected(): void
    {
        $sharedProduct = $this->product(7193, 'Burger enfant');

        $option = new ProductOption();
        $option->setAdditional(true);
        $option->addValue($this->valueLinkedTo('Poitrine fumée', $sharedProduct));
        $option->addValue($this->valueLinkedTo('Fromage', $sharedProduct));

        $constraint = new ProductOptionConstraint();
        $this->validator->validate($option, $constraint);

        $this->buildViolation($constraint->duplicateProductLink)
            ->atPath('property.path.values')
            ->setParameter('%product%', 'Burger enfant')
            ->assertRaised();
    }

    public function testValuesLinkedToDifferentProductsAreAllowed(): void
    {
        $option = new ProductOption();
        $option->setAdditional(true);
        $option->addValue($this->valueLinkedTo('Thé glacé pêche', $this->product(1, 'Thé glacé pêche')));
        $option->addValue($this->valueLinkedTo('Thé glacé mangue', $this->product(2, 'Thé glacé mangue')));

        $constraint = new ProductOptionConstraint();
        $this->validator->validate($option, $constraint);

        $this->assertNoViolation();
    }

    public function testValuesWithNoProductLinkAreAllowed(): void
    {
        $option = new ProductOption();
        $option->setAdditional(true);
        $option->addValue($this->valueLinkedTo('Poitrine fumée', null));
        $option->addValue($this->valueLinkedTo('Fromage', null));

        $constraint = new ProductOptionConstraint();
        $this->validator->validate($option, $constraint);

        $this->assertNoViolation();
    }

    private function product(int $id, string $name): Product
    {
        $product = new Product();
        $product->setFallbackLocale('fr');
        $product->setCurrentLocale('fr');
        $product->setName($name);

        $reflection = new \ReflectionProperty($product, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($product, $id);

        return $product;
    }

    private function valueLinkedTo(string $name, ?Product $product): ProductOptionValue
    {
        $value = new ProductOptionValue();
        $value->setFallbackLocale('fr');
        $value->setCurrentLocale('fr');
        $value->setValue($name);

        if ($product !== null) {
            $value->setProduct($product);
        }

        return $value;
    }
}
