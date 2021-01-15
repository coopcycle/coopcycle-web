<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\RemotePushToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RemotePushTokenInputDataTransformer implements DataTransformerInterface
{
    use TokenStorageTrait;

    public function __construct(TokenStorageInterface $tokenStorage, EntityManagerInterface $objectManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->objectManager = $objectManager;
    }

    /**
     * {@inheritdoc}
     */
    public function transform($data, string $to, array $context = [])
    {
        $remotePushToken = $this->objectManager->getRepository(RemotePushToken::class)
            ->findOneBy([
                'user' => $this->getUser(),
                'platform' => $data->platform
            ]);

        if ($remotePushToken) {
            $remotePushToken->setToken($data->token);
        } else {
            $remotePushToken = new RemotePushToken();
            $remotePushToken->setUser($this->getUser());
            $remotePushToken->setPlatform($data->platform);
            $remotePushToken->setToken($data->token);
        }

        return $remotePushToken;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof RemotePushToken) {
          return false;
        }

        return RemotePushToken::class === $to && null !== ($context['input']['class'] ?? null);
    }
}
