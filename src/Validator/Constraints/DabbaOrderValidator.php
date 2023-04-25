<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Dabba\Context as DabbaContext;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\OrderInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Validation;

class DabbaOrderValidator extends ConstraintValidator
{
    private $dabbaContext;
    private $logger;

    public function __construct(
        DabbaContext $dabbaContext,
        LoggerInterface $logger)
    {
        $this->dabbaContext = $dabbaContext;
        $this->logger = $logger;
    }

    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof OrderInterface) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of "%s"', OrderInterface::class));
        }

        if (!$object->isReusablePackagingEnabled()) {
            return;
        }

        $restaurant = $object->getRestaurant();

        if (null === $restaurant) {
            return;
        }

        if (!$restaurant->isDabbaEnabled()) {
            return;
        }

        $quantity = $object->getReusablePackagingQuantity();

        if ($quantity < 1) {
            $this->context->buildViolation($constraint->insufficientQuantity)
                ->atPath('reusablePackagingEnabled')
                ->addViolation();
            return;
        }

        try {

            $missing = $this->dabbaContext->getMissing($object);

            if ($missing > 0) {
                $this->context->buildViolation($constraint->insufficientWallet)
                    ->setParameter('%count%', $missing)
                    ->atPath('reusablePackagingEnabled')
                    ->addViolation();
            }

        } catch (RequestException $e) {

            $this->context->buildViolation($constraint->requestFailed)
                ->atPath('reusablePackagingEnabled')
                ->addViolation();

            $this->logger->error($e->getMessage());
        }
    }
}
