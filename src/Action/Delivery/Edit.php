<?php

namespace AppBundle\Action\Delivery;

use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use AppBundle\Entity\Delivery;
use AppBundle\Pricing\PricingManager;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Edit {
    public function __construct(
        private readonly PricingManager $pricingManager,
        private readonly ValidatorInterface $validator,
    )
    { }

    public function __invoke(Delivery $data): mixed
    {
        $errors = $this->validator->validate($data);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->pricingManager->updateDeliveryPrice($data);

        return $data;
    }
}
