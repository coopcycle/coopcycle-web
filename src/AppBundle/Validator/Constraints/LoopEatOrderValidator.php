<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Action\Utils\TokenStorageTrait;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Validation;

class LoopEatOrderValidator extends ConstraintValidator
{
    use TokenStorageTrait;

    private $client;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        LoopEatClient $client)
    {
        $this->tokenStorage = $tokenStorage;
        $this->client = $client;
    }

    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof OrderInterface) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of "%s"', OrderInterface::class));
        }

        $restaurant = $object->getRestaurant();

        if (null === $restaurant) {
            return;
        }

        if (!$restaurant->isLoopeatEnabled()) {
            return;
        }

        $quantity = $object->getReusablePackagingQuantity();

        if ($quantity < 1) {
            return;
        }

        $currentCustomer = $this->client->currentCustomer($this->getUser());
        $loopeatBalance = $currentCustomer['loopeatBalance'];

        if ($loopeatBalance < $quantity) {
            $this->context->buildViolation($constraint->insufficientBalance)
                ->atPath('reusablePackagingEnabled')
                ->addViolation();
        }
    }
}
