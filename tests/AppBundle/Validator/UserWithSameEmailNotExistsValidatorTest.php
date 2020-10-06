<?php

namespace AppBundle\Validator;

use AppBundle\Validator\Constraints\UserWithSameEmailNotExists as UserWithSameEmailNotExistsConstraint;
use AppBundle\Validator\Constraints\UserWithSameEmailNotExistsValidator;
use FOS\UserBundle\Model\UserManagerInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\ValidatorBuilder;

class UserWithSameEmailNotExistsValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    private $userManager;

    public function setUp() :void
    {
        $this->userManager = $this->prophesize(UserManagerInterface::class);

        parent::setUp();
    }

    protected function createValidator()
    {
        return new UserWithSameEmailNotExistsValidator(
            $this->userManager->reveal()
        );
    }

    public function testUserExists()
    {
        $user = $this->prophesize(UserInterface::class);

        $this->userManager
            ->findUserByEmail('dev@coopcycle.org')
            ->willReturn($user->reveal());

        $constraint = new UserWithSameEmailNotExistsConstraint();
        $violations = $this->validator->validate('dev@coopcycle.org', $constraint);

        $this->buildViolation($constraint->message)
            ->setCode(UserWithSameEmailNotExistsConstraint::USER_WITH_SAME_EMAIL_EXISTS_ERROR)
            ->assertRaised();
    }

    public function testUserNotExists()
    {
        $this->userManager
            ->findUserByEmail('dev@coopcycle.org')
            ->willReturn(null);

        $constraint = new UserWithSameEmailNotExistsConstraint();
        $violations = $this->validator->validate('dev@coopcycle.org', $constraint);

        $this->assertNoViolation();
    }
}
