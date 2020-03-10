<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;
use AppBundle\Entity\RemotePushToken;
use AppBundle\Action\Utils\TokenStorageTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class RemotePushTokenDataProvider implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    use TokenStorageTrait;

    private $objectManager;

    public function __construct(TokenStorageInterface $tokenStorage, EntityManagerInterface $objectManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->objectManager = $objectManager;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return RemotePushToken::class === $resourceClass && 'delete' === $operationName;
    }

    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = []): ?RemotePushToken
    {
        return $this->objectManager->getRepository(RemotePushToken::class)
            ->findOneBy([
                'user' => $this->getUser(),
                'token' => $id,
            ]);
    }
}
