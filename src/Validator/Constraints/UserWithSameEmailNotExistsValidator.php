<?php

namespace AppBundle\Validator\Constraints;

use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;

class UserWithSameEmailNotExistsValidator extends ConstraintValidator
{
    private $userManager;

    public function __construct(UserManagerInterface $userManager)
    {
        $this->userManager = $userManager;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('$value should be a string');
        }

        $user = $this->userManager->findUserByEmail($value);

        if (null !== $user) {
            $this->context->buildViolation($constraint->message)
                ->setCode(UserWithSameEmailNotExists::USER_WITH_SAME_EMAIL_EXISTS_ERROR)
                ->addViolation();
        }
    }
}
