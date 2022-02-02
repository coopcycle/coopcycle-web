<?php

namespace AppBundle\Command;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Bot;
use AppBundle\Entity\TaskList;
use AppBundle\Service\RoutingInterface;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use League\Geotools\Coordinate\Coordinate;
use League\Geotools\Geotools;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use M6Web\Bundle\DaemonBundle\Command\DaemonCommand;
use Polyline;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BotCommand extends DaemonCommand
{
    private $doctrine;
    private $tokenManager;
    private $routing;
    private $settingsManager;

    public function __construct(
        EntityManagerInterface $doctrine,
        JWTTokenManagerInterface $tokenManager,
        RoutingInterface $routing,
        SettingsManager $settingsManager,
        HttpClientInterface $apiClient)
    {
        $this->doctrine = $doctrine;
        $this->tokenManager = $tokenManager;
        $this->routing = $routing;
        $this->settingsManager = $settingsManager;
        $this->apiClient = $apiClient;

        $this->geotools = new Geotools();

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:bot')
            ->setDescription('Run bots.');
    }

    protected function setup(InputInterface $input, OutputInterface $output): void
    {
        // Set up your daemon here
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * Execute is called at every loop
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bots = $this->doctrine->getRepository(Bot::class)->findAll();

        $this->io->text(sprintf('Found %d bot(s)', count($bots)));

        $date = new \DateTime();

        $stopwatch = new Stopwatch();

        $stopwatch->start('main_loop');

        foreach ($bots as $bot) {

            $user = $bot->getUser();
            $token = $this->tokenManager->create($user);

            $tasks = $this->getTasks($token, $date);

            $this->logMessage($bot, sprintf('Found %d tasks for %s', count($tasks), $date->format('Y-m-d')));

            if (count($tasks) === 0) {
                $this->logMessage($bot, 'Nothing to do');
                continue;
            }

            $nextTask = null;
            foreach ($tasks as $task) {
                if ($task['status'] === 'TODO') {
                    $nextTask = $task;
                    break;
                }
            }

            if (null === $nextTask) {
                $this->logMessage($bot, 'Nothing to do');
                continue;
            }

            $this->logMessage($bot, sprintf('Next task is #%d', $nextTask['id']));

            $lastPosition = $bot->getLastPosition();
            if (null === $lastPosition) {
                $lastPosition = $this->getDefaultPosition();
            }

            $this->logMessage($bot, sprintf('Last position is %s, %s', $lastPosition->getLatitude(), $lastPosition->getLongitude()));

            $coords = [
                $lastPosition,
                new GeoCoordinates(
                    $nextTask['address']['geo']['latitude'],
                    $nextTask['address']['geo']['longitude']
                )
            ];

            $duration = $this->routing->getDuration(...$coords);
            $polyline = $this->routing->getPolyline(...$coords);

            $points = Polyline::decode($polyline);
            $points = Polyline::pair($points);

            do {

                $nextPoint = array_shift($points);

                if (null === $nextPoint) {
                    $nextPosition = null;
                    break;
                }

                $nextPosition = new GeoCoordinates($nextPoint[0], $nextPoint[1]);

            } while (!$this->isSignificantDistanceChange($lastPosition, $nextPosition));

            // We have arrived at destination
            if (null === $nextPosition) {
                $this->completeTask($token, $bot, $nextTask);
                continue;
            }

            $this->logMessage($bot, sprintf('Next position is %s, %s', $nextPosition->getLatitude(), $nextPosition->getLongitude()));

            $this->updateLocation($token, $bot, $nextPosition);
        }

        $this->doctrine->flush();

        $event = $stopwatch->stop('main_loop');

        $this->io->text((string) $event);

        // This method helps to give back the CPU to the react-loop.
        // So you can wait between two iterations if your workers has nothing to do.

        $this->setNextIterationSleepingTime(500000); // Every second

        return 0;
    }

    private function getDefaultPosition()
    {
        [ $latitude, $longitude ] = explode(',', $this->settingsManager->get('latlng'));

        return new GeoCoordinates($latitude, $longitude);
    }

    private function logMessage(Bot $bot, $message)
    {
        $this->io->text(sprintf('[%s] %s', $bot->getUser()->getUsername(), $message));
    }

    private function getTasks($token, \DateTime $date)
    {
        $tasks = [];

        try {

            $response = $this->apiClient->request('GET', '/api/me/tasks/'.$date->format('Y-m-d'), [
                'body' => '{}',
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/ld+json',
                    'Content-Type' => 'application/ld+json',
                ]
            ]);

            if (200 === $response->getStatusCode()) {
                $data = json_decode($response->getBody(), true);
                $tasks = $data['hydra:member'];
            }

        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }

        return $tasks;
    }

    private function completeTask($token, Bot $bot, $task)
    {
        try {

            $response = $this->apiClient->request('PUT', '/api/tasks/'.$task['id'].'/done', [
                'body' => '{}',
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/ld+json',
                    'Content-Type' => 'application/ld+json',
                ]
            ]);

            if (200 === $response->getStatusCode()) {
                $this->logMessage($bot, sprintf('Completed task #%d', $task['id']));
            }

        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }
    }

    private function updateLocation($token, Bot $bot, GeoCoordinates $nextPosition)
    {
        $payload = [];
        $payload[] = [
            'latitude' => $nextPosition->getLatitude(),
            'longitude' => $nextPosition->getLongitude(),
            'time' => (new \DateTime())->format(\DateTime::ATOM),
        ];

        try {

            $response = $this->apiClient->request('POST', '/api/me/location', [
                'body' => json_encode($payload),
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/ld+json',
                ]
            ]);

            if (200 === $response->getStatusCode()) {
                $bot->setLastPosition($nextPosition);
            }

        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }
    }

    private function isSignificantDistanceChange(GeoCoordinates $lastPosition, GeoCoordinates $nextPosition)
    {
        $lastCoordinate = new Coordinate([ $lastPosition->getLatitude(), $lastPosition->getLongitude() ]);
        $nextCoordinate = new Coordinate([ $nextPosition->getLatitude(), $nextPosition->getLongitude() ]);

        $distance = $this->geotools->distance()
            ->setFrom($lastCoordinate)
            ->setTo($nextCoordinate);

        return $distance->flat() > 5.0;
    }
}
