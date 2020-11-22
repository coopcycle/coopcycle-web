<?php

namespace AppBundle\Command;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use TeamTNT\TNTSearch\TNTSearch;
use TeamTNT\TNTSearch\Stemmer\PorterStemmer;

Class BuildIndexCommand extends ContainerAwareCommand
{
    private $connection;
    private $projectDir;

    public function __construct(Connection $connection, string $projectDir, string $sslmode)
    {
        $this->connection = $connection;
        $this->projectDir = $projectDir;
        $this->sslmode    = $sslmode;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:index:build')
            ->setDescription('Build TNTSearch index.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $tnt = new TNTSearch;

        $tntSearchDir = $this->projectDir . '/var/tntsearch';

        if (!file_exists($tntSearchDir)) {
            mkdir($tntSearchDir);
        }

        chmod($tntSearchDir, 0775);

        $tnt->loadConfig([
            'driver'    => 'pgsql',
            'host'      => $this->connection->getHost(),
            'port'      => $this->connection->getPort(),
            'database'  => $this->connection->getDatabase(),
            'username'  => $this->connection->getUsername(),
            'password'  => $this->connection->getPassword(),
            'sslmode'   => $this->sslmode,
            'storage'   => $tntSearchDir,
            'stemmer'   => PorterStemmer::class // optional
        ]);

        $indexer = $tnt->createIndex('restaurants.index');
        $indexer->query('SELECT id, name FROM restaurant;');
        $indexer->run();

        return 0;
    }
}
