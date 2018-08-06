<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @see https://afsy.fr/avent/2017/20-elasticsearch-6-et-symfony-4
 */
class SearchIndexCommand extends ContainerAwareCommand
{
    private $userManager;
    private $searchManager;

    protected function configure()
    {
        $this
            ->setName('coopcycle:search:index')
            ->setDescription('Build search indexes.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->userManager = $this->getContainer()->get('fos_user.user_manager');
        $this->searchManager = $this->getContainer()->get('coopcycle.search_manager');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $output->writeln('<info>Indexing users…</info>');
        $this->indexUsers($io);
    }

    private function indexUsers(SymfonyStyle $io)
    {
        $index = $this->searchManager->getUsersIndex();

        $users = $this->userManager->findUsers();

        $documents = [];
        foreach ($users as $user) {
            $documents[] = $this->searchManager->createDocumentFromUser($user);
        }

        $index->addDocuments($documents);
        $index->refresh();

        $io->success(sprintf('%d users indexed', count($documents)));
    }
}
