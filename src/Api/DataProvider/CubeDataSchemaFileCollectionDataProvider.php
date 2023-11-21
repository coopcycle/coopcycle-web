<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use AppBundle\Api\Resource\CubeDataSchemaFile;
use Symfony\Component\Finder\Finder;

final class CubeDataSchemaFileCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
	public function __construct(private string $schemaPath)
	{}

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return CubeDataSchemaFile::class === $resourceClass;
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = []): iterable
    {
    	$finder = new Finder();
		$finder->files()->in($this->schemaPath)->name('*.js');

		foreach ($finder as $file) {

			$schemaFile = new CubeDataSchemaFile();
        	$schemaFile->id = $file->getBasename('.js');

		    $contents = $file->getContents();

		    yield $schemaFile;
		}
    }
}
