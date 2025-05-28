<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Message\CopyProducts;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CopyProductsHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager)
    {}

    public function __invoke(CopyProducts $message)
    {
        $repository = $this->entityManager->getRepository(LocalBusiness::class);

        $src = $repository->find($message->getSrcId());
        $dest = $repository->find($message->getDestId());

        $repository->copyProducts($src, $dest);
    }
}

