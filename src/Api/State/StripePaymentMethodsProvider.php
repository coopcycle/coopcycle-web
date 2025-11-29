<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Dto\StripePaymentMethodsOutput;
use AppBundle\Service\StripeManager;
use Symfony\Component\Security\Core\Security;

final class StripePaymentMethodsProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private StripeManager $stripeManager)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $output = new StripePaymentMethodsOutput();

        $user = $this->security->getUser();

        if (null !== $user->getStripeCustomerId()) {

            $paymentMethods = $this->stripeManager->getCustomerPaymentMethods(
                $user->getStripeCustomerId()
            );

            foreach ($paymentMethods as $paymentMethod) {
                $output->addMethod($paymentMethod);
            }
        }

        return $output;
    }
}
