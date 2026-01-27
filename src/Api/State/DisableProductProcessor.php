<?php

namespace AppBundle\Api\State;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Symfony\Validator\Exception\ValidationException;
use AppBundle\Api\Dto\DisableProduct;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Message\EnableProduct;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class DisableProductProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ItemProvider $itemProvider,
        private readonly ProcessorInterface $persistProcessor,
        private readonly MessageBusInterface $messageBus)
    {}

    /**
     * @param DisableProduct $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Product */
        $product = $this->itemProvider->provide($operation, $uriVariables, $context);

        $product->setEnabled(false);

        try {
            $until = new \DateTimeImmutable($data->until);
        } catch (\Exception $e) {
            throw new ValidationException('Invalid date format for "until" parameter');
        }

        if ($until <= new \DateTimeImmutable()) {
            throw new ValidationException('The "until" date must be in the future');
        }

        // Schedule a message to re-enable the product later
        $this->messageBus->dispatch(new EnableProduct($product), [
            DelayStamp::delayUntil($until),
        ]);

        return $this->persistProcessor->process($product, $operation, $uriVariables, $context);
    }
}

