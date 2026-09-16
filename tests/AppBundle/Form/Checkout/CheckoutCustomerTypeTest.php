<?php

namespace Tests\AppBundle\Form\Checkout;

use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\User;
use AppBundle\Form\Checkout\CheckoutCustomerType;
use AppBundle\Form\Type\LegalType;
use AppBundle\Form\Type\PhoneNumberType;
use Nucleos\UserBundle\Util\Canonicalizer;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validation;

/**
 * Locks in the checkout-time backstop for a customer with no name captured
 * anywhere: whatever the reason — a guest with no account, a returning
 * guest, or a registered customer whose fullName was simply never asked for
 * (the web registration form has no name field at all — see RegistrationType)
 * — CheckoutCustomerType must add a required "fullName" field so checkout
 * can't complete without one. Zelty otherwise rejects the whole order push:
 * "customer.fname/name/company: Un de ces champs est requis".
 */
class CheckoutCustomerTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $checkoutCustomerType = new CheckoutCustomerType(
            $this->createMock(Canonicalizer::class),
            $this->createMock(RepositoryInterface::class)
        );
        $phoneNumberType = new PhoneNumberType('FR');
        $legalType = new LegalType($this->createMock(UrlGeneratorInterface::class));

        // The 'constraints' option (used both by CheckoutCustomerType's own
        // children and by AssertPhoneNumber/AssertUserWithSameEmailNotExists)
        // only exists once the validator extension is registered — we don't
        // need it to actually validate anything here, just to accept the option.
        $validator = Validation::createValidatorBuilder()->getValidator();

        return [
            new PreloadedExtension([$checkoutCustomerType, $phoneNumberType, $legalType], []),
            new ValidatorExtension($validator),
        ];
    }

    public function testBrandNewGuestWithNoCustomerAtAllIsAskedForAName(): void
    {
        $form = $this->buildCustomerForm(null);

        $this->assertTrue($form->has('fullName'));
        $this->assertTrue($this->isRequired($form->get('fullName')));
    }

    /**
     * A guest checkout is never backed by a User account, even if the same
     * email placed an order before and already gave a name that time — the
     * customer row itself never becomes a durable, verified identity, so
     * it's asked again on every guest order rather than trusted from state.
     */
    public function testReturningGuestWithoutAnAccountIsAskedAgainEvenIfAlreadyNamed(): void
    {
        $customer = new Customer();
        $customer->setFirstName('Jean');
        $customer->setLastName('Dupont');
        // No setUser(): this customer has no linked account.

        $form = $this->buildCustomerForm($customer);

        $this->assertTrue($form->has('fullName'));
        $this->assertTrue($this->isRequired($form->get('fullName')));
    }

    /**
     * The exact production bug: the web registration form never collected a
     * name, so a logged-in customer can reach checkout with an account but
     * an empty fullName. This must still be caught here, not silently
     * waved through because hasUser() is true.
     */
    public function testRegisteredCustomerWithNoNameIsAskedAtCheckout(): void
    {
        $customer = new Customer();
        $customer->setUser(new User());
        // firstName/lastName left null, exactly as a web signup leaves them.

        $form = $this->buildCustomerForm($customer);

        $this->assertTrue($form->has('fullName'));
        $this->assertTrue($this->isRequired($form->get('fullName')));
    }

    /**
     * The negative case, to guard the boundary: a registered customer who
     * already has a name on file isn't asked again.
     */
    public function testRegisteredCustomerWithANameIsNotAskedAgain(): void
    {
        $customer = new Customer();
        $customer->setUser(new User());
        $customer->setFirstName('Jean');
        $customer->setLastName('Dupont');

        $form = $this->buildCustomerForm($customer);

        $this->assertFalse($form->has('fullName'));
    }

    private function buildCustomerForm(?Customer $customer): FormInterface
    {
        $order = new class($customer) {
            public function __construct(private readonly ?Customer $customer) {}

            public function getCustomer(): ?Customer
            {
                return $this->customer;
            }
        };

        $form = $this->factory->createBuilder(FormType::class, $order)
            ->add('customer', CheckoutCustomerType::class, [
                'mapped' => false,
                // The real checkout form also passes 'constraints' =>
                // [new Assert\Valid()] here to cascade validation, but that
                // needs Symfony's ValidatorExtension wired in — irrelevant
                // to what this test checks (which fields get added).
                'data' => $customer,
            ])
            ->getForm();

        return $form->get('customer');
    }

    private function isRequired(FormInterface $field): bool
    {
        return $field->getConfig()->getOption('required');
    }
}
