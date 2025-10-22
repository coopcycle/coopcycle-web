<?php

namespace AppBundle\Validator\Constraints;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Validator\Exception\ValidationException;
use AppBundle\Api\Dto\DeliveryInputDto;
use AppBundle\Api\State\DeliveryProcessor;
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

                //Verify that the data is valid for both POST and PUT operations
                //POST format is used when re-calculating the price
                //PUT format is used when updating an existing delivery

                $postDelivery = $this->deliveryProcessor->process($data, new Post());

                $errors = $this->validator->validate($postDelivery);
                if (count($errors) > 0) {
                    throw new ValidationException($errors);
                }

                $putDelivery = $this->deliveryProcessor->process($data, new Put());

                $errors = $this->validator->validate($putDelivery);
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
