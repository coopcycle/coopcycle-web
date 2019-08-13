<?php

namespace AppBundle\Command;

use AppBundle\Entity\ApiLog;
use Doctrine\Common\Persistence\ObjectManager;
use Predis\Client as Redis;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\Factory as LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class FlushApiLogsCommand extends Command
{
    private $doctrine;
    private $redis;
    private $batchSize = 50;
    private $lockFactory;

    public function __construct(ObjectManager $doctrine, Redis $redis)
    {
        parent::__construct();

        $this->doctrine = $doctrine;
        $this->redis = $redis;
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:api:flush-logs')
            ->setDescription('Flushes API logs from Redis to insert in database.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $store = new FlockStore();
        $this->lockFactory = new LockFactory($store);

        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $lock = $this->lockFactory->createLock(__CLASS__);

        if (!$lock->acquire()) {
            $output->writeln('<error>Another command is running, aborting.</error>');
            return;
        }

        $length = $this->redis->llen('api:logs');

        $this->io->text(sprintf('Found %d lines of logs to flush', $length));

        $i = 0;
        while ($json = $this->redis->lpop('api:logs')) {

            $record = json_decode($json, true);

            $log = new ApiLog();

            $log->setCreatedAt(\DateTime::__set_state($record['datetime']));

            $log->setMethod($record['extra']['method']);
            $log->setRequestUri($record['extra']['request_uri']);
            $log->setStatusCode($record['extra']['status_code']);

            $log->setRequestHeaders($record['extra']['request_headers']);
            $log->setRequestBody($record['extra']['request_body']);

            $log->setResponseHeaders($record['extra']['response_headers']);
            $log->setResponseBody($record['extra']['response_body']);

            if (isset($record['extra']['token']) && !empty($record['extra']['token'])) {
                $log->setAuthenticated($record['extra']['token']['authenticated']);
                $log->setUsername($record['extra']['token']['username']);
                $log->setRoles($record['extra']['token']['roles']);
            }

            $this->doctrine->persist($log);

            if ((++$i % $this->batchSize) === 0) {
                $this->doctrine->flush();
                $this->doctrine->clear(ApiLog::class);
                $this->io->text('Flushing data…');
            }
        }

        $this->doctrine->flush();
        $this->doctrine->clear(ApiLog::class);

        $this->io->text('Finished flushing logs');
    }
}
