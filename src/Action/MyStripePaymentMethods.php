<?php

namespace AppBundle\Action;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Api\Dto\StripePaymentMethodsOutput;
use AppBundle\Service\StripeManager;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class MyStripePaymentMethods
{
    use TokenStorageTrait;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        StripeManager $stripeManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->stripeManager = $stripeManager;
    }

    public function __invoke()
    {
        $output = new StripePaymentMethodsOutput();
        if (null !== $this->getUser()->getStripeCustomerId()) {
            $paymentMethods = $this->stripeManager->getCustomerPaymentMethods(
                $this->getUser()->getStripeCustomerId()
            );

            foreach ($paymentMethods as $paymentMethod) {
                $output->addMethod($paymentMethod);
            }

            return $output;
        }
        return [];
    }
}
