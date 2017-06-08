<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AppBundle\Entity;

class ImportRestopolitanCommand extends ContainerAwareCommand
{
    private $restaurantManager;
    private $restaurantRepository;
    private $cuisineManager;
    private $cuisineRepository;

    protected function configure()
    {
        $this
            ->setName('app:restopolitan:import')
            ->setDescription('Imports restaurants from Restopolitan.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $doctrine = $this->getContainer()->get('doctrine');

        $this->restaurantManager = $doctrine->getManagerForClass('AppBundle:Restaurant');
        $this->restaurantRepository = $doctrine->getRepository('AppBundle:Restaurant');

        $this->cuisineManager = $doctrine->getManagerForClass('AppBundle:Cuisine');
        $this->cuisineRepository = $doctrine->getRepository('AppBundle:Cuisine');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dirname = $this->getContainer()->getParameter('kernel.root_dir') . '/../var/restopolitan';

        $files = glob($dirname . '/restaurants_*.json');
        foreach ($files as $filename) {

            $data = json_decode(file_get_contents($filename), true);

            $restaurants = $data['restaurants'];
            $output->writeln(sprintf('Loaded %d restaurants from JSON file %s', count($restaurants), basename($filename)));

            $this->importRestaurants($restaurants, $input, $output);
        }
    }

    private function importRestaurants(array $restaurants, InputInterface $input, OutputInterface $output)
    {
        $count = 0;
        foreach ($restaurants as $item) {

            if (empty($item['latlng'])) {
                $output->writeln(sprintf('<comment>No latlng for %s</comment>', $item['canonical_url']));
                continue;
            }

            if (empty($item['address'])) {
                $output->writeln(sprintf('<comment>No address for %s</comment>', $item['canonical_url']));
                continue;
            }

            $products = [];
            foreach ($item['menu'] as $menuItem) {
                foreach ($menuItem['dishes'] as $dish) {

                    if (empty($dish['price'])) {
                        continue;
                    }

                    $dishName = $dish['name'];
                    if (strlen($dishName) > 255) {
                        $dishName = substr($dishName, 0, 254).'…';
                    }

                    $price = trim(str_replace([',', '€'], ['.', ''], $dish['price']));
                    $product = new Entity\Product();
                    $product
                        ->setName($dishName)
                        ->setRecipeCategory($menuItem['category'])
                        ->setPrice($price);

                    $products[] = $product;
                }
            }

            if (count($products) === 0) {
                $output->writeln(sprintf('<comment>No products for %s</comment>', $item['canonical_url']));
                continue;
            }

            $streetAddress = str_replace("\n", ' ', $item['address']);
            list($latitude, $longitude) = explode(',', $item['latlng']);
            $website = !empty($item['website']) ? $item['website'] : null;

            $restaurant = new Entity\Restaurant();
            $restaurant
                ->setName($item['name'])
                ->setStreetAddress($streetAddress)
                // ->setAddressLocality($item['location'])
                // ->setServesCuisine($item['subCategory'])
                // ->setTelephone($telephone)
                ->setWebsite($website)
                ->setGeo(new Entity\Base\GeoCoordinates(trim($latitude), trim($longitude)));

            foreach ($item['cuisines'] as $cuisineItem) {
                $cuisineName = trim(rtrim($cuisineItem['name'], ' -'));
                if (!$cuisine = $this->cuisineRepository->findOneByName($cuisineName)) {
                    $cuisine = new Entity\Cuisine();
                    $cuisine->setName($cuisineName);

                    $this->cuisineManager->persist($cuisine);
                    $this->cuisineManager->flush();
                }

                $restaurant->addServesCuisine($cuisine);
            }

            foreach ($products as $product) {
                $restaurant->addProduct($product);
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
}
