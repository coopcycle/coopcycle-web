<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOptionValue;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\LifecycleEventArgs;

#[AsDoctrineListener(event: Events::postUpdate, connection: 'default')]
class DisabledProductListener
{
    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof Product) {

            /** @var \Doctrine\ORM\EntityManagerInterface */
            $objectManager = $args->getObjectManager();
            $unitOfWork = $objectManager->getUnitOfWork();

            $changeset = $unitOfWork->getEntityChangeSet($entity);

            if (isset($changeset['enabled'])) {
                [$oldValue, $newValue] = $changeset['enabled'];
                $optionValues = $objectManager->getRepository(ProductOptionValue::class)->findBy(['product' => $entity]);
                foreach ($optionValues as $optionValue) {
                    $optionValue->setEnabled($newValue);
                }
                $objectManager->flush();
            }
        }
    }
 }
