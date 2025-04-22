<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\CreateRemotePushTokenRequest;
use AppBundle\Entity\RemotePushToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class RemotePushTokenProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private ProcessorInterface $persistProcessor)
    {}

    /**
     * @var CreateRemotePushTokenRequest $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $user = $this->security->getUser();

        $remotePushToken = $this->entityManager->getRepository(RemotePushToken::class)
            ->findOneBy([
                'user' => $user,
                'platform' => $data->platform
            ]);

        if ($remotePushToken) {
            $remotePushToken->setToken($data->token);
        } else {
            $remotePushToken = new RemotePushToken();
            $remotePushToken->setUser($user);
            $remotePushToken->setPlatform($data->platform);
            $remotePushToken->setToken($data->token);
        }

        return $this->persistProcessor->process($remotePushToken, $operation, $uriVariables, $context);
    }
}
