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

    public function __construct(Client $client)
    {
        $this->client = $client;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('typesense:collections:create')
            ->setDescription('Creates al collections for Typesense')
            ->addArgument(
                'env'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $env = $input->getArgument('env');

        if ('test' === $env) {
            $schemas_dir = 'typesense/schemas';
            $schemas_files = array_diff(scandir($schemas_dir), array('..', '.')); // remove . and ..

            array_walk($schemas_files, function ($schema_file) use($schemas_dir) {
                $content = include($schemas_dir . '/' . $schema_file);

                $content['name'] = $content['name'] . '_test';

                try {
                    $this->client->collections->create($content);
                } catch (\Throwable $th) {
                    $this->io->text(sprintf('There was an error creating the collection %s - %s', $content['name'], $th->getMessage()));
                    return 0;
                }
            });

            $this->io->text('All collections have been created');
        }

        return 1;
    }

}
