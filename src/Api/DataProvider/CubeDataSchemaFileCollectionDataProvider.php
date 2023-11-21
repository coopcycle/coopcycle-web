<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use AppBundle\Api\Resource\CubeDataSchemaFile;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CubeDataSchemaFileCollectionDataProvider implements
    ContextAwareCollectionDataProviderInterface,
    RestrictedDataProviderInterface
{
    public function __construct(
        private string $schemaPath,
        private CacheInterface $appCache)
    {}

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return CubeDataSchemaFile::class === $resourceClass;
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = []): iterable
    {
        $filenames = $this->appCache->get('cube.data_schema_files', function (ItemInterface $item) {

            $item->expiresAfter(60 * 60 * 24);

            $finder = new Finder();
            $finder->files()->in($this->schemaPath)->name('*.js');

            $filenames = [];
            foreach ($finder as $file) {
                $filenames[] = $file->getBasename();
            }

            return $filenames;
        });

        foreach ($filenames as $filename) {

            $schemaFile = new CubeDataSchemaFile($filename);

            yield $schemaFile;
        }
    }
}
