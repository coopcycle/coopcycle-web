<?php

namespace AppBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Id\IdentityGenerator;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Sylius\Component\Resource\Model\ResourceInterface;

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

        if (!$metadata->getReflectionClass()->implementsInterface(ResourceInterface::class)) {
            return;
        }

        if (!$metadata->isIdGeneratorIdentity()) {
            $metadata->setIdGenerator(new IdentityGenerator($metadata->sequenceGeneratorDefinition['sequenceName']));
            $metadata->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_IDENTITY);
        }
    }
}
