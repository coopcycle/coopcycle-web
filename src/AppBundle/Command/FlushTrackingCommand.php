<?php

namespace AppBundle\Command;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\TrackingPosition;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\Factory as LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class FlushTrackingCommand extends ContainerAwareCommand
{
    private $doctrine;
    private $redis;
    private $userManager;
    private $batchSize = 50;
    private $lockFactory;

    protected function configure()
    {
        $this
            ->setName('coopcycle:tracking:flush')
            ->setDescription('Flushes tracking history from Redis to insert in database.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->doctrine = $this->getContainer()->get('doctrine');
        $this->redis = $this->getContainer()->get('snc_redis.default');
        $this->userManager = $this->getContainer()->get('fos_user.user_manager');

        $this->em = $this->doctrine->getManagerForClass(TrackingPosition::class);

        $store = new FlockStore();
        $this->lockFactory = new LockFactory($store);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $lock = $this->lockFactory->createLock('orm-purger');

        if (!$lock->acquire()) {
            $output->writeln('<error>Database is being purged, aborting.</error>');
            return;
        }

        $keys = $this->redis->keys('tracking:*');

        if (count($keys) === 0) {
            $output->writeln('<info>Nothing to do!</info>');
            return;
        }

        $usernames = [];
        foreach ($keys as $key) {
            preg_match('/:([^:]+)$/', $key, $matches);
            $username = $matches[1];
            if ($user = $this->userManager->findUserByUsername($username)) {
                $this->flushTracking($user, $key, $output);
            } else {
                $output->writeln(sprintf('<error>User %s does not exist</error>', $username));
            }
        }
    }

    private function flushTracking(UserInterface $user, $key, OutputInterface $output)
    {
        $key = str_replace($this->redis->getOptions()->prefix->getPrefix(), '', $key);

        $length = $this->redis->llen($key);
        $output->writeln(sprintf('<info>Found %d events to flush for user %s</info>', $length, $user->getUsername()));

        $i = 0;
        while ($json = $this->redis->lpop($key)) {

            $data = json_decode($json, true);

            $date = new \DateTime();
            $date->setTimestamp((int) $data['timestamp']);

            $trackingPosition = new TrackingPosition();
            $trackingPosition->setCourier($user);
            $trackingPosition->setCoordinates(new GeoCoordinates($data['latitude'], $data['longitude']));
            $trackingPosition->setDate($date);

            $this->em->persist($trackingPosition);

            if ((++$i % $this->batchSize) === 0) {
                $this->em->flush();
                $this->em->clear(TrackingPosition::class);
                $output->writeln('<info>Flushing data…</info>');
            }
        }

        $this->em->flush();
        $this->em->clear(TrackingPosition::class);

        $output->writeln(sprintf('<info>Finished flushing data for user %s</info>', $user->getUsername()));
    }
}
