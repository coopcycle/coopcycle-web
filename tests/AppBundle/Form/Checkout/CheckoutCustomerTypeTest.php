<?php

namespace Tests\AppBundle\Form\Checkout;

use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\User;
use AppBundle\Form\Checkout\CheckoutCustomerType;
use AppBundle\Form\Type\LegalType;
use AppBundle\Form\Type\PhoneNumberType;
use AppBundle\Validator\Constraints\UserWithSameEmailNotExistsValidator;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Nucleos\UserBundle\Util\Canonicalizer;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\ConstraintValidatorFactory;
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

    /**
     * Reproduces the production bug directly, at submit time: a guest fills
     * in the required fullName field, but their email already matches an
     * existing, nameless Customer row in the database (e.g. one left over
     * from a web signup that never collected a name). The SUBMIT listener
     * swaps in that old row (as it must, to avoid creating a duplicate
     * customer) and must carry the freshly-typed fullName onto it —
     * mirroring the existing phoneNumber carry-over right above it — or the
     * order ends up with the stale, empty name Zelty then rejects.
     */
    public function testFullNameJustTypedByAGuestIsAppliedToTheMatchedExistingCustomer(): void
    {
        $existingCustomer = new Customer();
        $existingCustomer->setEmail('vincecru@hotmail.fr');
        $existingCustomer->setEmailCanonical('vincecru@hotmail.fr');
        // No name at all: the production starting state.

        $canonicalizer = $this->createMock(Canonicalizer::class);
        $canonicalizer->method('canonicalize')->willReturnArgument(0);

        $customerRepository = $this->createMock(RepositoryInterface::class);
        $customerRepository->method('findOneBy')
            ->with(['emailCanonical' => 'vincecru@hotmail.fr'])
            ->willReturn($existingCustomer);

        $factory = $this->buildFactory(new CheckoutCustomerType($canonicalizer, $customerRepository));

        $order = new class(null) {
            public function __construct(private readonly ?Customer $customer) {}

            public function getCustomer(): ?Customer
            {
                return $this->customer;
            }
        };

        $form = $factory->createBuilder(FormType::class, $order)
            ->add('customer', CheckoutCustomerType::class, [
                'mapped' => false,
                'data' => null,
            ])
            ->getForm();

        $form->submit([
            'customer' => [
                'email' => 'vincecru@hotmail.fr',
                'fullName' => 'Vincent Crucifère',
                'phoneNumber' => '+33670278006',
                'legal' => '1',
            ],
        ]);

        $resultingCustomer = $form->get('customer')->getData();

        $this->assertSame($existingCustomer, $resultingCustomer, 'The matched, pre-existing row must still be reused.');
        $this->assertSame('Vincent Crucifère', $resultingCustomer->getFullName());
    }

    private function buildFactory(CheckoutCustomerType $checkoutCustomerType): FormFactoryInterface
    {
        $phoneNumberType = new PhoneNumberType('FR');
        $legalType = new LegalType($this->createMock(UrlGeneratorInterface::class));

        // UserWithSameEmailNotExistsValidator needs a UserManager injected by
        // the container in real life; a plain Validation::createValidator()
        // can't construct it on its own, so it's pre-supplied here — its
        // actual behavior is irrelevant to this test, which only submits a
        // brand-new email that no user has.
        $userWithSameEmailValidator = new UserWithSameEmailNotExistsValidator(
            $this->createMock(UserManagerInterface::class)
        );
        $validator = Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                UserWithSameEmailNotExistsValidator::class => $userWithSameEmailValidator,
            ]))
            ->getValidator();

        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([$checkoutCustomerType, $phoneNumberType, $legalType], []))
            ->addExtension(new ValidatorExtension($validator))
            ->getFormFactory();
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
