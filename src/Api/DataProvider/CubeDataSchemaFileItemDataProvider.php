<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use AppBundle\Api\Resource\CubeDataSchemaFile;
use Symfony\Component\Finder\Finder;

final class CubeDataSchemaFileItemDataProvider implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
	public function __construct(private string $schemaPath)
	{}

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return CubeDataSchemaFile::class === $resourceClass;
    }

    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = []): ?CubeDataSchemaFile
    {
    	$finder = new Finder();
        $finder->files()->in($this->schemaPath)->name($id . '.js');

        if (!$finder->hasResults()) {
		    // TODO 404
		}

		$file = current(iterator_to_array($finder));

		$schemaFile = new CubeDataSchemaFile();
		$schemaFile->id = $file->getBasename('.js');
		$schemaFile->contents = $file->getContents();

        return $schemaFile;
    }
}
