<?php

namespace AppBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class StandtrackIUBRangeSetupCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        ?string $standtrackApiKey
    ) {
        parent::__construct();
        if (is_null($standtrackApiKey)) {
            throw new \Exception('Missing Standtrack API key');
        }
    }

    protected function configure(): void
    {
        $this
            ->setName('coopcycle:standtrack:iub_range_setup')
            ->setDescription('Sets up the IUB range for Standtrack');
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $from = $helper->ask(
            $input,
            $output,
            new Question('From: ', null)
        );

        $to = $helper->ask(
            $input,
            $output,
            new Question('To: ', null)
        );

        $stmt = $this->em->getConnection()
            ->prepare(
                'CREATE SEQUENCE IF NOT EXISTS standtrack_iub_seq MINVALUE :from MAXVALUE :to;',
            );
        $ctn = $stmt->executeQuery([':from' => (int)$from, ':to' => (int)$to])->fetchOne();

        return 0;
    }
}
