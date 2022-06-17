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
            // ->addArgument(
            //     'env'
            // )
            ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // $env = $input->getArgument('env');

        $schemas_files = array_diff(scandir($this->schemasDir), array('..', '.'));

        print_r($schemas_files);
        print_r($this->collections);
        return 0;

        if ('test' === $env) {
            $schemas_files = array_diff(scandir($this->schemasDir), array('..', '.')); // remove . and ..

            array_walk($schemas_files, function ($schema_file) use($schemas_dir) {
                $content = include($this->schemasDir . '/' . $schema_file);

                $content['name'] = $content['name'] . '_test';

                try {
                    $this->client->collections->create($content);
                } catch (\Throwable $th) {
                    $this->io->text(sprintf('There was an error creating the collection %s - %s', $content['name'], $th->getMessage()));
                }
            });

            $this->io->text('All collections have been created');
        }

        return 0;
    }

}
