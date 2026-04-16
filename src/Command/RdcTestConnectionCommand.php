<?php

namespace AppBundle\Command;

use AppBundle\Integration\Rdc\Api\RdcClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'rdc:test-connection',
    description: 'Test RDC integration connection (Keycloak token retrieval)',
)]
class RdcTestConnectionCommand extends Command
{
    public function __construct(
        private readonly RdcClientInterface $rdcClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('RDC Connection Test');

        $io->section('Testing Keycloak Token Retrieval');

        try {
            $io->text('Requesting token from Keycloak...');

            $token = $this->rdcClient->getToken();

            $io->success('Token retrieved successfully!');
            $io->text(sprintf('Token (first 50 chars): %s...', substr($token, 0, 50)));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to retrieve token');
            $io->text('Error: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
