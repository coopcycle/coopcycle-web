<?php

namespace AppBundle\Command;

use AppBundle\Integration\Rdc\Api\RdcClientFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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
        private readonly RdcClientFactory $rdcClientFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('connection-id', InputArgument::OPTIONAL, 'RDC connection ID from RDC_CONNECTIONS');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $connectionId = $input->getArgument('connection-id');
        $availableConnections = $this->rdcClientFactory->getConnectionIds();

        if (empty($availableConnections)) {
            $io->error('No RDC connections configured. Set RDC_CONNECTIONS in your environment.');
            return Command::FAILURE;
        }

        if ($connectionId === null) {
            $io->title('RDC Connection Test');
            $io->text('Available connections: ' . implode(', ', $availableConnections));
            $connectionId = $io->ask('Which connection ID to test?', $availableConnections[0]);
        }

        $rdcClient = $this->rdcClientFactory->create($connectionId);
        if ($rdcClient === null) {
            $io->error(sprintf('Connection "%s" not found or disabled', $connectionId));
            return Command::FAILURE;
        }

        $io->success(sprintf('Using connection: %s', $connectionId));
        $io->section('Testing Keycloak Token Retrieval');

        try {
            $io->text('Requesting token from Keycloak...');

            $token = $rdcClient->getToken();

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
