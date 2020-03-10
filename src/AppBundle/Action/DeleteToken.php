<?php

namespace AppBundle\Action;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\RemotePushToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DeleteToken
{
    use TokenStorageTrait;

    protected $objectManager;

    public function __construct(TokenStorageInterface $tokenStorage, EntityManagerInterface $objectManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->objectManager = $objectManager;
    }

    public function __invoke($token)
    {
        $remotePushToken = $this->objectManager->getRepository(RemotePushToken::class)
            ->findOneBy([
                'user' => $this->getUser(),
                'token' => $token,
            ]);

        if ($remotePushToken) {
            $this->objectManager->remove($remotePushToken);
            $this->objectManager->flush();
            return;
        }

        throw new NotFoundHttpException(sprintf('Token with value "%s" does not exist', $token));
    }
}
