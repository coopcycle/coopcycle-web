<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Sylius\Product;
use AppBundle\Message\EnableProduct;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class EnableProductHandler
{
    public function __construct(private EntityManagerInterface $entityManager)
    {}

    public function __invoke(EnableProduct $message)
    {
        $product = $this->entityManager->getRepository(Product::class)->find($message->id);

        if (null === $product) {
            return;
        }

        $product->setEnabled(true);

        $this->entityManager->flush();
    }
}

