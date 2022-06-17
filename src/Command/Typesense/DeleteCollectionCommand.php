<?php

namespace AppBundle\Command\Typesense;

use AppBundle\Typesense\CollectionManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteCollectionCommand extends Command
{
    public function __construct(CollectionManager $collectionManager)
    {
        $this->collectionManager = $collectionManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('typesense:collection:delete')
            ->setDescription('Deletes a collection in Typesense')
            ->addArgument(
                'collection',
                InputArgument::REQUIRED
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('collection');

        try {
            $this->collectionManager->delete($name);
        } catch (\Throwable $th) {
            $this->io->text(sprintf('There was an error deleting the collection: %s', $th->getMessage()));
        }

        $this->io->text(sprintf('Collection %s deleted successfully', $name));

        return 0;
    }

}
