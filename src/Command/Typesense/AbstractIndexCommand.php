<?php

namespace AppBundle\Command\Typesense;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractIndexCommand extends Command
{
    protected $typesenseClient;
    protected $entityManager;
    protected $serializer;

    public function __construct()
    {
        parent::__construct();
    }

    public function configure()
    {
        // Override this method
    }

    abstract protected function getDocumentsToIndex();

    public function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $documents = $this->getDocumentsToIndex();

            $result = $this->typesenseClient->createMultiDocuments($documents);

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
                $documents = $this->typesenseClient->deleteMultiDocuments();

                $this->io->text('Documents indexed successfully have ben removed, fix the error and try to index all the documents again');
            } else {
                $this->io->text('All data has been indexed successfully');

                $collectionData = $this->typesenseClient->getCollection();

                $this->io->text(sprintf('%s collection now has %d documents indexed', $this->typesenseClient->getCollectionName(), $collectionData['num_documents']));
            }

        } catch (\Throwable $th) {
            $this->io->text(sprintf('There was an error indexing data: %s', $th->getMessage()));
        }

        return 0;
    }

}
