<?php

namespace AppBundle\Command;

use AppBundle\Entity\Store;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

Class CopyStoreAddressesCommand extends Command
{
    private $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:stores:copy_addresses')
            ->setDescription('Copy store addresses to another store.')
            ->addArgument(
                'from',
                InputArgument::REQUIRED
            )
            ->addArgument(
                'to',
                InputArgument::REQUIRED
            );
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
        $from = $this->entityManager->getRepository(Store::class)->find($input->getArgument('from'));
        $to   = $this->entityManager->getRepository(Store::class)->find($input->getArgument('to'));

        if ((null !== $from && null !== $to) && $from !== $to) {
            $addresses = $from->getAddresses();
            foreach ($addresses as $address) {
                if ($address !== $from->getAddress()) {
                    $newAddress = clone $address;
                    $to->addAddress($newAddress);
                }
            }
        }

        $this->entityManager->flush();

        return 0;
    }
}

