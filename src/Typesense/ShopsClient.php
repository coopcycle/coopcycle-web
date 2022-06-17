<?php

namespace AppBundle\Typesense;

use Typesense\Client as TypesenseClient;

class ShopsClient
{
    public function __construct(
        TypesenseClient $typesenseClient,
        string $collectionName
    )
    {
        $this->typesenseClient = $typesenseClient;
        $this->collectionName = $collectionName;
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

    public function search($searchParameters)
    {
        return $this->typesenseClient
            ->collections[$this->getCollectionName()]
            ->documents->search($searchParameters);
    }

    public function getCollectionName()
    {
        return $this->collectionName;
    }
}
