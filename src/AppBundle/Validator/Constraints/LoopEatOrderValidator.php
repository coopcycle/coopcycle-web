<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\OrderInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Validation;
use Webmozart\Assert\Assert as WebmozartAssert;

class LoopEatOrderValidator extends ConstraintValidator
{
    private $client;

    public function __construct(
        LoopEatClient $client,
        LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
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

        try {

            $customer = $object->getCustomer();

            WebmozartAssert::isInstanceOf($customer, CustomerInterface::class);

            if (!$customer->hasUser()) {
                return;
            }

            $currentCustomer = $this->client->currentCustomer($customer->getUser());
            $loopeatBalance = $currentCustomer['loopeatBalance'];

            if ($loopeatBalance < $quantity) {
                $this->context->buildViolation($constraint->insufficientBalance)
                    ->atPath('reusablePackagingEnabled')
                    ->addViolation();
            }
        } catch (RequestException $e) {
            $this->logger->error($e->getMessage());
        }

    }
}
