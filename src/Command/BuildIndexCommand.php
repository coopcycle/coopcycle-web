<?php

namespace AppBundle\Command;

use Doctrine\DBAL\Connection;
use Psonic\Client;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

Class BuildIndexCommand extends ContainerAwareCommand
{
    private $connection;
    private $ingestClient;
    private $controlClient;
    private $sonicSecretPassword;
    private $namespace;

    public function __construct(Connection $connection, Client $ingestClient, Client $controlClient, string $sonicSecretPassword, string $namespace)
    {
        $this->connection = $connection;
        $this->ingestClient = $ingestClient;
        $this->controlClient = $controlClient;
        $this->sonicSecretPassword = $sonicSecretPassword;
        $this->namespace = $namespace;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:index:build')
            ->setDescription('Build Sonic index.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ingest  = new \Psonic\Ingest($this->ingestClient);
        $control = new \Psonic\Control($this->controlClient);

        $ingest->connect($this->sonicSecretPassword);
        $control->connect($this->sonicSecretPassword);

        $stmt = $this->connection->executeQuery('SELECT id, name FROM restaurant');

        $this->io->text(sprintf('Indexing restaurants into collection "restaurants" of bucket "%s"', $this->namespace));

        while ($restaurant = $stmt->fetch()) {
            $response = $ingest->push('restaurants', $this->namespace, $restaurant['id'], $restaurant['name']);
            $status = $response->getStatus(); // Should be "OK"
        }

        $control->consolidate();

        $ingest->disconnect();
        $control->disconnect();

        return 0;
    }
}
