<?php

namespace AppBundle\Command\Typesense;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Typesense\Client;

class CreateCollectionCommand extends Command
{
    public function __construct(Client $client, string $schemasDir)
    {
        $this->client = $client;
        $this->schemasDir = $schemasDir;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('typesense:collection:create')
            ->setDescription('Creates a collection in Typesense')
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
        $collection = $input->getArgument('collection');

        try {
            $contents = include_once(sprintf('%s/%s.php', $this->schemasDir, $collection));

            $this->client->collections->create($contents);

        } catch (\Throwable $th) {
            $this->io->text(sprintf('There was an error creating the collection: %s', $th->getMessage()));
        }

        $this->io->text(sprintf('Schema for %s created successfully', $collection));

        return 0;
    }

}
