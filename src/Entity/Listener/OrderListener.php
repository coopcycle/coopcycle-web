<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Vendor;
use Dflydev\Base32\Crockford\Crockford;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

class OrderListener
{
    public function preFlush(Order $order, PreFlushEventArgs $args)
    {
        $entityManager = $args->getEntityManager();
        $vendor = $order->getVendor();

        if (null !== $vendor) {
            if (!$entityManager->contains($vendor)) {
                $params = $vendor->isHub() ?
                    ['hub' => $vendor->getHub()] : ['restaurant' => $vendor->getRestaurant()];

                $existingVendor = $entityManager->getRepository(Vendor::class)
                    ->findOneBy($params);

                if (null !== $existingVendor) {
                    $order->setVendor($existingVendor);
                } else {
                    $entityManager->persist($vendor);
                    $entityManager->flush();
                }
            }
        }
    }

    public function preUpdate(Order $order, PreUpdateEventArgs $args)
    {
        $objectManager = $args->getObjectManager();

        if ($args->hasChangedField('number')) {
            $number = $args->getNewValue('number');

            if (null !== $number) {
                $numberWithoutCollision = $this->findNumberWithoutCollision($objectManager, $number);

                if ($numberWithoutCollision !== $number) {
                    $args->setNewValue('number', $numberWithoutCollision);
                }
            }
        }
    }

    private function findNumberWithoutCollision(EntityManagerInterface $objectManager, string $number, int $depth = 10)
    {
        if ($depth <= 0) {
            throw new \Exception('Could not find a unique number');
        }

        $orderRepository = $objectManager->getRepository(Order::class);
        $orderWithSameNumber = $orderRepository->findOneByNumber($number);

        if (null === $orderWithSameNumber) {
            return $number;
        }

        $nextId = $orderRepository->fetchNextSeqId();
        $newNumber = Crockford::encode($nextId);
        return $this->findNumberWithoutCollision($objectManager, $newNumber, $depth - 1);
    }
}
