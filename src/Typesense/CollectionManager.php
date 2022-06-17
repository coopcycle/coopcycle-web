<?php

namespace AppBundle\Typesense;

use Typesense\Client;
use Typesense\Exceptions\ObjectNotFound;

class CollectionManager
{
    public function __construct(Client $client, string $schemasDir, array $collections)
    {
        $this->client = $client;
        $this->schemasDir = $schemasDir;
        $this->collections = $collections;
    }

    public function getCollections()
    {
        return array_keys($this->collections);
    }

    public function hasCollection($name)
    {
        return isset($this->collections[$name]);
    }

    public function getCollectionName($name)
    {
        return $this->collections[$name] ?? $name;
    }

    public function create($name)
    {
        $schemaFile = sprintf('%s/%s.php', $this->schemasDir, $name);

        // Resolve name with namespace
        $collectionName = $this->getCollectionName($name);

        try {
            return $this->client->collections[$collectionName]->retrieve();
        } catch (ObjectNotFound) {
            if (file_exists($schemaFile) && $this->hasCollection($name)) {

                $schema = include($schemaFile);
                $schema['name'] = $collectionName;

                return $this->client->collections->create($schema);
            }
        }
    }

    public function delete($name)
    {
        // Resolve name with namespace
        $collectionName = $this->getCollectionName($name);

        return $this->client->collections[$collectionName]->delete();
    }
}
