<?php

namespace AppBundle\Command;

use AppBundle\Service\EdenredManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

Class EdenredRestaurantsReceiveCommand extends Command
{
    private $edenredManager;
    private $io;

    public function __construct(
        EdenredManager $edenredManager
    )
    {
        $this->edenredManager = $edenredManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:edenred:restaurants-receive')
            ->setDescription('Read XMLs from FTP server of Edenred for restaurants synchronisation.')
            ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io->text('Searching for XML files at Edenred FTP server for restaurants synchronisation');

        $this->edenredManager->readEdenredFileAndSynchronise();

        return 0;
    }

}
