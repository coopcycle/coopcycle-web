<?php

namespace AppBundle\Validator\Constraints;

use ApiPlatform\Validator\Exception\ValidationException;
use AppBundle\Api\Dto\DeliveryInputDto;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class IncidentMetadataValidator extends ConstraintValidator
{
    public function __construct(
        private readonly DenormalizerInterface $denormalizer,
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
                $data = $this->denormalizer->denormalize(
                    $item['suggestion'],
                    DeliveryInputDto::class,
                    'json'
                );

                $errors = $this->validator->validate($data);
                if (count($errors) > 0) {
                    throw new ValidationException($errors);
                }

                if ((null === $data->tasks || count($data->tasks) === 0) && (null === $data->order)) {
                    throw new ValidationException('The suggestion field in metadata must contain at least one task or an order');
                }

            } catch (\Throwable $e) {
                $this->logger->warning('The suggestion field in metadata is not valid: '. $e->getMessage());
                $this->context
                    ->buildViolation($constraint->invalidSuggestionMessage)
                    ->atPath("[{$index}][suggestion]")
                    ->addViolation();
            }
        }
    }
}
