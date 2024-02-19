<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\LoopEat\Client as LoopeatClient;
use AppBundle\LoopEat\Context as LoopeatContext;
use AppBundle\LoopEat\GuestCheckoutAwareAdapter as LoopEatAdapter;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\PriceFormatter;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Validation;

class LoopEatOrderValidator extends ConstraintValidator
{
    private $client;
    private $logger;

    public function __construct(
        LoopeatClient $client,
        LoopeatContext $loopeatContext,
        PriceFormatter $priceFormatter,
        LoggerInterface $logger)
    {
        $this->client = $client;
        $this->loopeatContext = $loopeatContext;
        $this->priceFormatter = $priceFormatter;
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

        if (!$restaurant->isLoopeatEnabled()) {
            return;
        }

        $quantity = $object->getReusablePackagingQuantity();

        if ($quantity < 1) {

            $this->context->buildViolation($constraint->insufficientQuantity)
                ->atPath('reusablePackagingEnabled')
                ->addViolation();
            return;
        }

        $adapter = new LoopEatAdapter($object);

        try {

            $currentCustomer = $this->client->currentCustomer($adapter);
            $requiredAmount  = $object->getRequiredAmountForLoopeat();
            $returnsAmount   = $object->getReturnsAmountForLoopeat();

            $missing = $requiredAmount - ($currentCustomer['credits_count_cents'] + $returnsAmount);

            if ($missing > 0) {

                $this->context->buildViolation($constraint->insufficientBalance)
                    ->setParameter('%name%', $this->loopeatContext->name)
                    ->setParameter('%amount%', $this->priceFormatter->formatWithSymbol($missing))
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
