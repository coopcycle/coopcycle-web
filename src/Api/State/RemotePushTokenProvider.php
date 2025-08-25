<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Entity\RemotePushToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

final class RemotePushTokenProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return $this->entityManager->getRepository(RemotePushToken::class)
            ->findOneBy([
                'user' => $this->security->getUser(),
                'token' => $uriVariables['token'],
            ]);
    }
}
