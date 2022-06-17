<?php

namespace AppBundle\Command\Typesense;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Typesense\Client;

class CreateAllCollectionsCommand extends Command
{
    public function __construct(Client $client, string $schemasDir, array $collections)
    {
        $this->client = $client;
        $this->schemasDir = $schemasDir;
        $this->collections = $collections;

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
        foreach ($this->collections as $name => $nameWithNamespace) {

            $schemaFile = sprintf('%s/%s.php', $this->schemasDir, $name);

            if (file_exists($schemaFile)) {

                $schema = include($schemaFile);

                try {
                    $this->client->collections->create($schema);
                } catch (\Throwable $e) {
                    $this->io->text(sprintf('There was an error creating the collection %s - %s', $name, $e->getMessage()));
                }
            }

        }

        $this->io->text('All collections have been created');

        return 0;
    }

}
