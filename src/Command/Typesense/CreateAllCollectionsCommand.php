<?php

namespace AppBundle\Command\Typesense;

use AppBundle\Typesense\CollectionManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateAllCollectionsCommand extends Command
{
    public function __construct(CollectionManager $collectionManager)
    {
        $this->collectionManager = $collectionManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('typesense:collections:create')
            ->setDescription('Creates all collections for Typesense')
            ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        foreach ($this->collectionManager->getCollections() as $name) {
            try {
                $collection = $this->collectionManager->create($name);
                $this->io->text(
                    sprintf('Created collection "%s" with name "%s"', $name, $collection['name'])
                );
            } catch (\Throwable $e) {
                $this->io->text(sprintf('There was an error creating the collection %s - %s', $name, $e->getMessage()));
            }
        }

        return 0;
    }

}
