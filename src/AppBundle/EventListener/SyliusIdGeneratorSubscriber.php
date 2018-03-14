<?php

namespace AppBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Id\IdentityGenerator;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * This class is needed because Sylius uses the "AUTO" strategy, which doesn't work in PostgreSQL.
 */
class SyliusIdGeneratorSubscriber implements EventSubscriber
{
    public function getSubscribedEvents()
    {
        return array(
            'loadClassMetadata',
        );
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args)
    {
        $metadata = $args->getClassMetadata();

        $classes = [
            'Sylius\Component\Order\Model\Adjustment',
            'Sylius\Component\Order\Model\Order',
            'Sylius\Component\Order\Model\OrderItem',
            'Sylius\Component\Order\Model\OrderItemUnit',
            'Sylius\Component\Order\Model\OrderSequence',
            'Sylius\Component\Taxation\Model\TaxCategory',
            'Sylius\Component\Taxation\Model\TaxRate',
        ];

        if (!in_array($metadata->getName(), $classes)) {
            return;
        }

        if (!$metadata->isIdGeneratorIdentity()) {
            $metadata->setIdGenerator(new IdentityGenerator($metadata->sequenceGeneratorDefinition['sequenceName']));
            $metadata->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_IDENTITY);
        }
    }
}
