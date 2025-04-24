<?php

namespace AppBundle\Api\DataPersister;

use ApiPlatform\Core\DataPersister\DataPersisterInterface;
use ApiPlatform\Validator\ValidatorInterface;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\PackageSet;
use AppBundle\Entity\TimeSlot;
use AppBundle\Entity\Warehouse;
use Doctrine\ORM\EntityManagerInterface;

final class DeleteValidationAwareDataPersister implements DataPersisterInterface
{
    public function __construct(
        private DataPersisterInterface $dataPersister,
        private ValidatorInterface $validator)
    {}

    public function persist($data)
    {
        $this->dataPersister->persist($data);
    }

    public function remove($data)
    {
        $this->validator->validate($data, ['groups' => ['deleteValidation']]);
        $this->dataPersister->remove($data);
    }

    public function supports($data): bool
    {
        return $data instanceof TimeSlot || $data instanceof PackageSet || $data instanceof PricingRuleSet || $data instanceof Warehouse;
    }
}

