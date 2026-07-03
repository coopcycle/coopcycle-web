<?php

namespace AppBundle\MessageHandler\SoftDelete;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Message\SoftDelete\CleanupProductOption;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CleanupProductOptionHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger)
    {
    }

    public function __invoke(CleanupProductOption $message)
    {
        $productOptionRepository = $this->entityManager->getRepository(ProductOption::class);

        $entity = $productOptionRepository->find($message->getProductOptionId());
        if (null === $entity) {
            return;
        }

        $this->logger->debug(sprintf('CleanupProductOptionHandler: cleaning up ProductOption#%d', $entity->getId()));

        $productRepository = $this->entityManager->getRepository(Product::class);
        $restaurantRepository = $this->entityManager->getRepository(LocalBusiness::class);

        // Remove associations Product <-> ProductOption
        // Make sure to also delete disabled options from Product
        $filterCollection = $this->entityManager->getFilters();
        if ($filterCollection->isEnabled('disabled_filter')) {
            $filterCollection->disable('disabled_filter');
        }

        $products = $productRepository->findByOption($entity);
        foreach ($products as $product) {
            foreach ($product->getProductOptions() as $productOption) {
                if ($productOption->getOption() === $entity) {
                    $this->logger->debug(sprintf('CleanupProductOptionHandler: removing ProductOption#%d from Product#%d',
                        $entity->getId(), $product->getId()));
                    $this->entityManager->remove($productOption);
                }
            }
        }

        $restaurants = $restaurantRepository->findByOption($entity);
        foreach ($restaurants as $restaurant) {
            $this->logger->debug(sprintf('CleanupProductOptionHandler: removing ProductOption#%d from LocalBusiness#%d',
                $entity->getId(), $restaurant->getId()));
            $restaurant->removeProductOption($entity);
        }

        $this->entityManager->flush();
    }
}
