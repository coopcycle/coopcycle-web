<?php

namespace AppBundle\Validator\Constraints;

use ApiPlatform\Metadata\Put;
use ApiPlatform\Symfony\Validator\Exception\ValidationException;
use AppBundle\Api\Dto\DeliveryInputDto;
use AppBundle\Api\State\DeliveryProcessor;
use AppBundle\Entity\Delivery;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class IncidentMetadataValidator extends ConstraintValidator
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly DeliveryProcessor $deliveryProcessor,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof IncidentMetadata) {
            throw new \InvalidArgumentException(sprintf('$constraint should be an instance of %s', IncidentMetadata::class));
        }

        if (null === $value || !is_array($value)) {
            return;
        }

        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            if (!isset($item['suggestion'])) {
                continue;
            }

            // Check if the suggestion is a valid delivery
            try {
                /** @var DeliveryInputDto $data */
                $data = $this->serializer->denormalize(
                    $item['suggestion'],
                    DeliveryInputDto::class,
                    'json'
                );

                /** @var Delivery $delivery */
                $delivery = $this->deliveryProcessor->process($data, new Put(), [
                    'id' => $data->id
                ]);

                $errors = $this->validator->validate($delivery);
                if (count($errors) > 0) {
                    throw new ValidationException($errors);
                }

            } catch (\Throwable $e) {
                $this->logger->warning('The suggestion field in metadata is not a valid delivery: '. $e->getMessage());
                $this->context
                    ->buildViolation($constraint->invalidSuggestionMessage)
                    ->atPath("[{$index}][suggestion]")
                    ->addViolation();
            }
        }
    }
}
