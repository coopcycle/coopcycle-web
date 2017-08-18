<?php

namespace AppBundle\Command;

use Faker;
use MarkovPHP;
use Nelmio\Alice\Fixtures\Loader as FixturesLoader;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AppBundle\Entity;

class InitDemoCommand extends ContainerAwareCommand
{
    private $userManipulator;
    private $doctrine;
    private $faker;
    private $fixturesLoader;
    private $menuSections = [];

    private static $users = [
        'admin' => [
            'password' => 'admin',
            'roles' => ['ROLE_ADMIN']
        ],
        'restaurant' => [
            'password' => 'restaurant',
            'roles' => ['ROLE_RESTAURANT']
        ]
    ];

    protected function configure()
    {
        $this
            ->setName('coopcycle:demo:init')
            ->setDescription('Initialize CoopCycle demo.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->userManipulator = $this->getContainer()->get('fos_user.util.user_manipulator');
        $this->doctrine = $this->getContainer()->get('doctrine');

        $this->faker = Faker\Factory::create('fr_FR');
        $this->fixturesLoader = new FixturesLoader('fr_FR');

        $restaurantProvider = new RestaurantProvider($this->faker);

        $this->faker->addProvider($restaurantProvider);
        $this->fixturesLoader->addProvider($restaurantProvider);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Creating users...');
        foreach (self::$users as $username => $params) {
            $this->createUser($username, $params);
        }

        $output->writeln('Creating restaurants...');
        $this->createRestaurants($output);
    }

    private function createUser($username, $params)
    {
        $this->userManipulator->create($username, $params['password'], "{$username}@coopcycle.org", true, false);
        foreach ($params['roles'] as $role) {
            $this->userManipulator->addRole($username, $role);
        }
    }

    private function createMenuSection($name)
    {
        $menuSection = new Entity\MenuSection();
        $menuSection->setName($name);

        $this->doctrine->getManagerForClass(Entity\MenuSection::class)->persist($menuSection);

        return $menuSection;
    }

    private function createMenu()
    {
        $this->fixturesLoader->setReferences($this->menuSections);
        $objects = $this->fixturesLoader->load(__DIR__ . '/Resources/menu.yml');

        return $objects['menu'];
    }

    private function createRestaurant(Entity\Address $address)
    {
        $restaurant = new Entity\Restaurant();

        $restaurant->setAddress($address);
        $restaurant->setMenu($this->createMenu());
        $restaurant->setName($this->faker->restaurantName);
        $restaurant->addOpeningHour('Mo,Sa 10:00-19:00');

        return $restaurant;
    }

    private function createRestaurants(OutputInterface $output)
    {
        $this->menuSections = [
            'menu_section_appetizers' => $this->createMenuSection('Entrées'),
            'menu_section_dishes' => $this->createMenuSection('Plats'),
            'menu_section_desserts' => $this->createMenuSection('Desserts'),
        ];

        $addresses = $this->fixturesLoader->load(__DIR__ . '/Resources/addresses.yml');

        foreach ($addresses as $key => $address) {
            if (0 === strpos($key, 'address_')) {
                $restaurant = $this->createRestaurant($address);
                $this->doctrine->getManagerForClass(Entity\Restaurant::class)->persist($restaurant);
            }
        }

        $this->doctrine->getManagerForClass(Entity\Restaurant::class)->flush();
    }
}
