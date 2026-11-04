<?php

namespace AppBundle\Command;

use AppBundle\Integration\Rdc\Api\RdcClientFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DebugRdcGetCommand extends Command
{
    public function __construct(
        private readonly RdcClientFactory $rdcClientFactory,
        private readonly bool $rdcEnabled = false,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('debug:rdc-get')
            ->setDescription('Debug: GET a remote URL via RdcClient and output headers and body')
            ->addArgument('url', InputArgument::REQUIRED, 'The remote URL to GET')
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'The RDC connection to GET with, e.g. BOL.MEMBER.NOWASTEPHARMA (defaults to the first one)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->rdcEnabled) {
            $io->error('RDC feature is disabled');

            return Command::INVALID;
        }

        $url = $input->getArgument('url');

        $io->title(sprintf('GET %s', $url));

        $connectionId = $input->getOption('connection');
        $rdcClient = is_null($connectionId)
            ? $this->rdcClientFactory->getDefaultClient()
            : $this->rdcClientFactory->create($connectionId);
        if (is_null($rdcClient)) {
            $io->error(sprintf(
                'No RDC client available, configured connections: %s',
                implode(', ', $this->rdcClientFactory->getConnectionIds()) ?: 'none'
            ));

            return Command::FAILURE;
        }

        try {
            $response = $rdcClient->getRemote($url);

            $statusCode = $response->getStatusCode();
            $io->section('Status Code');
            $io->text(sprintf('%d', $statusCode));

            $io->section('Headers');
            foreach ($response->getHeaders(false) as $name => $values) {
                foreach ($values as $value) {
                    $io->text(sprintf('%s: %s', $name, $value));
                }
            }

            $io->section('Body');
            $body = $response->getContent();
            $io->text($body);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('Error: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
