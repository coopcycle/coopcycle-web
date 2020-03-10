<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;
use AppBundle\Entity\RemotePushToken;
use Doctrine\ORM\EntityManagerInterface;

final class RemotePushTokenDataProvider implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    private $objectManager;

    public function __construct(EntityManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return RemotePushToken::class === $resourceClass && 'delete' === $operationName;
    }

    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = []): ?RemotePushToken
    {
        return $this->objectManager->getRepository(RemotePushToken::class)->findOneByToken($id);
    }
}
