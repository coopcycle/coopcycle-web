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
    public function __construct(
        private LoopeatClient $client,
        private LoopeatContext $loopeatContext,
        private PriceFormatter $priceFormatter,
        private LoggerInterface $logger)
    {}

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

        $isMandatory = $restaurant->isLoopeatMandatory();
        $isEnabled   = $object->isReusablePackagingEnabled();

        // When zero waste is optional and the customer opted out, there is nothing to validate.
        if (!$isMandatory && !$isEnabled) {
            return;
        }

        // When zero waste is mandatory, the customer must not be able to opt out
        // (this can happen through the API, or on the web if the checkbox is bypassed).
        if ($isMandatory && !$isEnabled) {

            $this->addViolation($constraint->mandatory, $isMandatory, [
                '%name%' => $this->loopeatContext->name,
            ]);
            return;
        }

        // From here on, reusable packaging is enabled: make sure the customer has
        // connected their account and has a sufficient balance.
        $quantity = $object->getReusablePackagingQuantity();

        if ($quantity < 1) {

            $this->addViolation($constraint->insufficientQuantity, $isMandatory);
            return;
        }

        $adapter = new LoopEatAdapter($object);

        // The customer must have connected their zero waste account before
        // being able to proceed to checkout (this is required to settle the deposit).
        if (!$adapter->hasLoopEatCredentials()) {

            $this->addViolation($constraint->accountNotConnected, $isMandatory, [
                '%name%' => $this->loopeatContext->name,
            ]);
            return;
        }

        try {

            $currentCustomer = $this->client->currentCustomer($adapter);
            $requiredAmount  = $object->getRequiredAmountForLoopeat();
            $returnsAmount   = $object->getReturnsAmountForLoopeat();

            $missing = $requiredAmount - ($currentCustomer['credits_count_cents'] + $returnsAmount);

            if ($missing > 0) {

                $this->addViolation($constraint->insufficientBalance, $isMandatory, [
                    '%name%'   => $this->loopeatContext->name,
                    '%amount%' => $this->priceFormatter->formatWithSymbol($missing),
                ]);
            }

        } catch (RequestException $e) {

            $this->addViolation($constraint->requestFailed, $isMandatory, [
                '%name%' => $this->loopeatContext->name,
            ]);

            $this->logger->error($e->getMessage());
        }
    }

    /**
     * Adds a violation for the reusable packaging option.
     *
     * When zero waste is mandatory, the "reusablePackagingEnabled" checkbox is rendered
     * as disabled. Symfony discards validation errors mapped to disabled fields (they
     * always report as valid), so the violation would silently not block the checkout.
     * In that case we attach the violation to the root form instead — it is displayed by
     * `form_errors(form)`. Otherwise we keep it on the field, for an inline message.
     */
    private function addViolation(string $message, bool $isMandatory, array $parameters = []): void
    {
        $builder = $this->context->buildViolation($message);

        foreach ($parameters as $key => $value) {
            $builder->setParameter($key, $value);
        }

        if (!$isMandatory) {
            $builder->atPath('reusablePackagingEnabled');
        }

        $builder->addViolation();
    }
}
