<?php

namespace AppBundle\Doctrine\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Sylius\Component\Resource\Metadata\MetadataInterface;
use Sylius\Component\Resource\Metadata\RegistryInterface;
use Sylius\Component\Resource\Model\TranslatableInterface;
use Sylius\Component\Resource\Model\TranslationInterface;

/**
 * @see https://gist.github.com/kunicmarko20/74118570887c4bf067160536e49737d3
 */
final class CachingTranslatableSubscriber implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        return [
            Events::loadClassMetadata,
        ];
    }

    /**
     * Add mapping to translatable entities
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();
        $reflection = $classMetadata->getReflectionClass();

        if ($reflection->isAbstract()) {
            return;
        }

        if ($reflection->implementsInterface(TranslatableInterface::class)) {
            $this->mapTranslatable($classMetadata);
        }

        if ($reflection->implementsInterface(TranslationInterface::class)) {
            $this->mapTranslation($classMetadata);
        }
    }

    /**
     * Add mapping data to a translatable entity.
     */
    private function mapTranslatable(ClassMetadata $metadata): void
    {
        $className = $metadata->name;

        $metadata->enableCache(['usage' => ClassMetadataInfo::CACHE_USAGE_NONSTRICT_READ_WRITE]);

        if ($metadata->hasAssociation('translations')) {
            $metadata->enableAssociationCache('translations', [
                'usage' => ClassMetadataInfo::CACHE_USAGE_NONSTRICT_READ_WRITE,
                'region' => 'sylius_translations',
            ]);
        }
    }

    /**
     * Add mapping data to a translation entity.
     */
    private function mapTranslation(ClassMetadata $metadata): void
    {
        $className = $metadata->name;

        $metadata->enableCache([
            'usage' => ClassMetadataInfo::CACHE_USAGE_NONSTRICT_READ_WRITE,
            'region' => 'sylius_translations',
        ]);
    }
}
