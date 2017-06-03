<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AppBundle\Entity;

class ImportRestaurantsCommand extends ContainerAwareCommand
{
    private $restaurantManager;
    private $restaurantRepository;
    private $skipSubCategories = [
        'Art Gallery',
        'Bakery',
        'Bar',
        'Café',
        'Candy Store',
        'Clothing Store',
        'Cocktail Bar',
        'Coffee Shop',
        'Concert Hall',
        'Coworking Space',
        'Event Space',
        'Fast Food Restaurant',
        'Gourmet Shop',
        'Hotel',
        'Hotel Bar',
        'Ice Cream Shop',
        'Jazz Club',
        // 'Lounge',
        'Office',
        'Pub',
        'Tea Room',
        'Tech Startup',
    ];

    protected function configure()
    {
        $this
            ->setName('app:import-restaurants')
            ->setDescription('Imports restaurants from TourPedia.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->restaurantManager = $this->getContainer()->get('doctrine')->getManagerForClass('AppBundle\\Entity\\Restaurant');
        $this->restaurantRepository = $this->restaurantManager->getRepository('AppBundle\\Entity\\Restaurant');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dirname = $this->getContainer()->getParameter('kernel.root_dir') . '/../var/tourpedia';

        if (!file_exists($dirname)) {
            mkdir($dirname, 0655, true);
        }

        $filename = "{$dirname}/restaurants.json";

        if (!file_exists($filename)) {
            $data = file_get_contents('http://tour-pedia.org/api/getPlaces?category=restaurant&location=Paris');
            file_put_contents($filename, $data);
        }

        $restaurants = json_decode(file_get_contents($filename), true);

        $output->writeln(sprintf('Loaded %d restaurants from JSON file', count($restaurants)));

        $count = 0;
        foreach ($restaurants as $item) {

            if (!isset($item['subCategory'])) {
                continue;
            }

            if (in_array($item['subCategory'], $this->skipSubCategories)) {
                continue;
            }

            $details = json_decode(file_get_contents($item['details']), true);

            if (!$restaurant = $this->restaurantRepository->findOneByName($item['name'])) {

                $output->writeln(sprintf('Adding restaurant "%s"', $item['name']));

                $streetAddress = !empty($item['address']) ? $item['address'] : null;
                $telephone = !empty($item['phone_number']) ? $item['phone_number'] : null;
                $website = !empty($details['website']) ? $details['website'] : null;

                $restaurant = new Entity\Restaurant();
                $restaurant
                    ->setName($item['name'])
                    ->setStreetAddress($streetAddress)
                    ->setAddressLocality($item['location'])
                    ->setServesCuisine($item['subCategory'])
                    ->setTelephone($telephone)
                    ->setWebsite($website)
                    ->setGeo(new Entity\Base\GeoCoordinates($item['lat'], $item['lng']));
            }

            if (count($restaurant->getProducts()) === 0) {
                // $output->writeln(sprintf('Adding products to restaurant "%s"', $restaurant->getName()));
                $this->addProducts($restaurant);
            } else {
                // $output->writeln(sprintf('Skipping restaurant "%s"', $item['name']));
                $this->restaurantManager->detach($restaurant);
                continue;
            }

            $this->restaurantManager->persist($restaurant);
            $count++;

            if ($this->getScheduledOperations() > 100) {
                $this->restaurantManager->flush();
                $this->restaurantManager->clear();
                $output->writeln('Flush...');
            }
        }

        $output->writeln('');
        $output->writeln(sprintf('%d restaurants created!', $count));

        $this->restaurantManager->flush();
    }

    private function getScheduledOperations()
    {
        return count($this->restaurantManager->getUnitOfWork()->getScheduledEntityInsertions())
            + count($this->restaurantManager->getUnitOfWork()->getScheduledEntityUpdates())
            + count($this->restaurantManager->getUnitOfWork()->getScheduledCollectionUpdates());
    }

    private function addProducts(Entity\Restaurant $restaurant)
    {
        $pizza = new Entity\Product();
        $pizza
            ->setName('Pizza')
            ->setPrice(12.90);

        $hamburger = new Entity\Product();
        $hamburger
            ->setName('Hamburger')
            ->setPrice(10.90);

        $salad = new Entity\Product();
        $salad
            ->setName('Salad')
            ->setPrice(4.90);

        $restaurant->addProduct($pizza);
        $restaurant->addProduct($hamburger);
        $restaurant->addProduct($salad);
    }
}
