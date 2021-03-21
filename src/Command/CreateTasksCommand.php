<?php

namespace AppBundle\Command;

use AppBundle\Entity\Task;
use AppBundle\Faker\AddressProvider;
use AppBundle\Service\Geocoder;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Faker;
use Geocoder\Provider\Addok\Addok as AddokProvider;
use Geocoder\Provider\Chain\Chain as ChainProvider;
use Geocoder\Provider\Photon\Photon as PhotonProvider;
use Geocoder\StatefulGeocoder;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Http\Adapter\Guzzle6\Client;
use League\Geotools\Coordinate\Coordinate;
use Spatie\GuzzleRateLimiterMiddleware\RateLimiterMiddleware;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

Class CreateTasksCommand extends Command
{
    private $batchSize = 10;

    public function __construct(
        EntityManagerInterface $entityManager,
        Faker\Generator $faker,
        Geocoder $geocoder,
        SettingsManager $settingsManager,
        string $country,
        string $defaultLocale)
    {
        $this->entityManager = $entityManager;
        $this->faker = $faker;
        $this->geocoder = $geocoder;
        $this->settingsManager = $settingsManager;
        $this->country = $country;
        $this->defaultLocale = $defaultLocale;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:tasks:create')
            ->setDescription('Creates random tasks.')
            ->addOption(
                'date',
                null,
                InputOption::VALUE_REQUIRED,
                'Date to create tasks',
                'now',
            )
            ->addOption(
                'count',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of tasks to create',
                '100'
            )
            ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        // We create a custom geocoder chain using free services

        $stack = HandlerStack::create();
        $stack->push(RateLimiterMiddleware::perSecond(2));

        $httpClient  = new GuzzleClient(['handler' => $stack, 'timeout' => 30.0]);
        $httpAdapter = new Client($httpClient);

        $providers = [];

        if ('fr' === $this->country) {
            $providers[] = AddokProvider::withBANServer($httpAdapter);
        }
        $providers[] = PhotonProvider::withKomootServer($httpAdapter);

        $statefulGeocoder =
            new StatefulGeocoder(new ChainProvider($providers), $this->defaultLocale);

        $this->geocoder->setGeocoder($statefulGeocoder);

        $mapCenterValue = explode(',', $this->settingsManager->get('latlng'));

        $addressProvider = new AddressProvider($this->faker, $this->geocoder, new Coordinate($mapCenterValue));

        $this->faker->addProvider($addressProvider);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $date = $input->getOption('date');
        $count = intval($input->getOption('count'));

        $output->writeln(sprintf('Generating %d tasks', $count));

        $date = new \DateTime($date);

        $after  = clone $date;
        $before = clone $date;

        $after->setTime(0, 0, 0);
        $before->setTime(23, 59, 59);

        for ($i = 0; $i < $count; $i++) {

            $task = new Task();
            $task->setAddress($this->faker->randomAddress);
            $task->setAfter($after);
            $task->setBefore($before);

            $this->entityManager->persist($task);

            if (($i % $this->batchSize) === 0) {

                $output->writeln('Flushing data…');

                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        $this->entityManager->flush();

        return 0;
    }
}
