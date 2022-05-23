<?php

namespace AppBundle\Typesense;

use Typesense\Client as TypesenseClient;

class ShopsClient
{
    const COLLECTION = 'shops';

    public function __construct(
        TypesenseClient $typesenseClient,
        string $environment
    )
    {
        $this->typesenseClient = $typesenseClient;
        $this->environment = $environment;
    }

    public function createMultiDocuments($documents)
    {
        return $this->typesenseClient
            ->collections[$this->getCollectionName()]
            ->documents->import($documents, ['action' => 'create']);
    }

    public function createDocument($document)
    {
        return $this->typesenseClient
            ->collections[$this->getCollectionName()]
            ->documents->create($document);
    }

    public function updateDocument($documentId, $content)
    {
        return $this->typesenseClient
            ->collections[$this->getCollectionName()]
            ->documents[$documentId]->update($content);
    }

    public function deleteDocument($documentId)
    {
        $this->typesenseClient
            ->collections[$this->getCollectionName()]
            ->documents[$documentId]->delete();
    }

    public function deleteMultiDocuments()
    {
        $this->typesenseClient
            ->collections[$this->getCollectionName()]
            ->documents->delete(['filter_by' => 'enabled:[true,false]']);
    }

    public function getCollection()
    {
        return $this->typesenseClient
            ->collections[$this->getCollectionName()]->retrieve();
    }

    public function getCollectionName()
    {
        if ('test' === $this->environment) {
            return self::COLLECTION . '_test';
        }
        return self::COLLECTION;
    }
}
