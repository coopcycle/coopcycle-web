<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AppBundle\Entity;

class ImportRestaurantsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:import-restaurants')
            ->setDescription('Imports restaurants from TourPedia.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dirname = $this->getContainer()->getParameter('kernel.root_dir') . '/../var/tourpedia';

        if (!file_exists($dirname)) {
            mkdir($dirname, 0655, true);
        }

        $filename = "{$dirname}/restaurants.csv";

        if (!file_exists($filename)) {
            $data = file_get_contents('http://tour-pedia.org/api/getPlaces?category=restaurant&location=Paris');
            file_put_contents($filename, $data);
        }

        $restaurants = json_decode(file_get_contents($filename), true);

        $restaurantManager = $this->getContainer()->get('doctrine')->getManagerForClass('AppBundle\\Entity\\Restaurant');
        $restaurantRepository = $restaurantManager->getRepository('AppBundle\\Entity\\Restaurant');

        $i = 0;
        foreach ($restaurants as $item) {

            $restaurant = new Entity\Restaurant();
            $restaurant
                ->setName($item['name'])
                ->setStreetAddress($item['address'])
                ->setAddressLocality($item['location'])
                ->setGeo(new Entity\GeoCoordinates($item['lat'], $item['lng']));

            if (!$restaurantRepository->findOneByName($restaurant->getName())) {
                $output->writeln(sprintf('Saving restaurant "%s"', $restaurant->getName()));
                $restaurantManager->persist($restaurant);
                if ((++$i % 20) === 0) {
                    $restaurantManager->flush();
                    $output->writeln('Flush...');
                }
            }
        }

        $restaurantManager->flush();
    }
}