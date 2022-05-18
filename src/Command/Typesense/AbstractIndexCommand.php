<?php

namespace AppBundle\Command\Typesense;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Typesense\Client;

abstract class AbstractIndexCommand extends Command
{
    protected $COLLECTION_NAME = ''; // Override this property

    protected $client;
    protected $entityManager;
    protected $serializer;

    public function __construct(
        Client $client,
        EntityManagerInterface $entityManager,
        NormalizerInterface $serializer
    )
    {
        $this->client = $client;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;

        parent::__construct();
    }

    public function configure()
    {
        // Override this method
    }

    abstract protected function getDocumentsToIndex();

    // To delete all documents in a collection, you can use a filter that matches all documents in your collection.
    abstract protected function deleteIndexedDocuments();

    public function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $documents = $this->getDocumentsToIndex();

            $result = $this->client->collections[$this->COLLECTION_NAME]->documents->import($documents, ['action' => 'create']);

            $documentsWithErrors = array_filter($result, function($documentResult) {
                return !$documentResult['success'];
            });

            if (!empty($documentsWithErrors)) {
                $this->io->text('Error trying to index data');

                array_walk($documentsWithErrors, function ($doc) {
                    $error = sprintf('Document: %s - Error %s', $doc['document'], $doc['error']);
                    $this->io->text($error);
                });


                // if there was an error we should delete all indexed documents by this operation
                // fix the error and then try to index all the documents again
                $documents = $this->deleteIndexedDocuments($this->COLLECTION_NAME);

                $this->io->text('Documents indexed successfully have ben removed, fix the error and try to index all the documents again');
            } else {
                $this->io->text('All data has been indexed successfully');

                $collectionData = $this->client->collections[$this->COLLECTION_NAME]->retrieve();

                $this->io->text(sprintf('%s collection now has %d documents indexed', $this->COLLECTION_NAME, $collectionData['num_documents']));
            }

        } catch (\Throwable $th) {
            $this->io->text(sprintf('There was an error indexing data: %s', $th->getMessage()));
            return 0;
        }

        return 1;
    }

}
