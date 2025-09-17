<?php

namespace AppBundle\Api\State\Woopit;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Entity\Delivery;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DeliveryProvider implements ProviderInterface
{
    public function __construct(
        private Hashids $hashids12,
        private EntityManagerInterface $entityManager)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $decoded = $this->hashids12->decode($uriVariables['deliveryId']);

        if (count($decoded) !== 1) {
            throw new NotFoundHttpException('Delivery id not found');
        }

        $id = current($decoded);

        $delivery = $this->entityManager->getRepository(Delivery::class)->find($id);

        if (!$delivery) {
            throw new NotFoundHttpException('Delivery not found');
        }

        return $delivery;
    }
}
