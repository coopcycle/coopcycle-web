<?php

namespace AppBundle\Command;

use AppBundle\Entity;
use AppBundle\Faker\AddressProvider;
use AppBundle\Faker\RestaurantProvider;
use Faker;
use GuzzleHttp\Client;
use Nelmio\Alice\Fixtures\Loader as FixturesLoader;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class InitDemoCommand extends ContainerAwareCommand
{
    private $userManipulator;
    private $doctrine;
    private $faker;
    private $fixturesLoader;
    private $client;
    private $redis;

    private static $users = [
        'admin' => [
            'password' => 'admin',
            'roles' => ['ROLE_ADMIN']
        ],
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

        $client = new Client([
            'base_uri' => 'https://maps.googleapis.com',
            'timeout'  => 2.0,
        ]);

        $apiKey = $this->getContainer()->getParameter('google_api_key');
        $addressProvider = new AddressProvider($this->faker, $client, $apiKey);

        $this->faker->addProvider($restaurantProvider);
        $this->fixturesLoader->addProvider($restaurantProvider);

        $this->faker->addProvider($addressProvider);

        $this->redis = $this->getContainer()->get('snc_redis.default');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Creating super users...');
        foreach (self::$users as $username => $params) {
            $this->createUser($username, $params);
        }

        $output->writeln('Creating users...');
        for ($i = 1; $i <= 50; $i++) {
            $username = "user-{$i}";
            $user = $this->createUser($username, ['password' => $username]);
            $user->addAddress($this->faker->randomAddress);
        }
        $this->doctrine->getManagerForClass(Entity\ApiUser::class)->flush();

        $output->writeln('Creating couriers...');
        for ($i = 1; $i <= 50; $i++) {
            $this->createCourier("bot-{$i}");
        }

        $output->writeln('Creating restaurants...');
        $this->createRestaurants($output);

        $output->writeln('Removing data from Redis...');
        $keys = $this->redis->keys('*');
        foreach ($keys as $key) {
            $this->redis->del($key);
        }
    }

    private function createUser($username, array $params = [])
    {
        $user = $this->userManipulator->create($username, $params['password'], "{$username}@demo.coopcycle.org", true, false);
        if (isset($params['roles'])) {
            foreach ($params['roles'] as $role) {
                $this->userManipulator->addRole($username, $role);
            }
        }

        return $user;
    }

    private function createCourier($username)
    {
        $this->userManipulator->create($username, $username, "{$username}@demo.coopcycle.org", true, false);
        $this->userManipulator->addRole($username, 'ROLE_COURIER');
    }

    private function createTaxCategory()
    {
        $taxCategoryFactory = $this->getContainer()->get('sylius.factory.tax_category');
        $taxCategoryManager = $this->getContainer()->get('sylius.manager.tax_category');
        $taxRateFactory = $this->getContainer()->get('sylius.factory.tax_rate');
        $taxRateManager = $this->getContainer()->get('sylius.manager.tax_rate');

        $taxCategory = $taxCategoryFactory->createNew();
        $taxCategory->setName('TVA consommation immédiate');
        $taxCategory->setCode('tva_conso_immediate');

        $taxCategoryManager->persist($taxCategory);
        $taxCategoryManager->flush();

        $taxRate = $taxRateFactory->createNew();
        $taxRate->setName('TVA 10%');
        $taxRate->setCode('tva_10');
        $taxRate->setCategory($taxCategory);
        $taxRate->setAmount(10.00);
        $taxRate->setIncludedInPrice(true);
        $taxRate->setCalculator('default');

        $taxRateManager->persist($taxRate);
        $taxRateManager->flush();

        return $taxCategory;
    }

    private function createMenuCategory($name)
    {
        $category = new Entity\Menu\MenuCategory();
        $category->setName($name);

        $this->doctrine->getManagerForClass(Entity\Menu\MenuCategory::class)->persist($category);

        return $category;
    }

    private function createMenu(TaxCategoryInterface $taxCategory)
    {
        $objects = $this->fixturesLoader->load(__DIR__ . '/Resources/menu.yml');

        $menu = $objects['menu'];

        foreach ($menu->getAllItems() as $menuItem) {
            $menuItem->setTaxCategory($taxCategory);
        }

        foreach ($menu->getAllModifiers() as $menuItemModifier) {
            foreach ($menuItemModifier->getModifierChoices() as $modifier) {
                $modifier->setTaxCategory($taxCategory);
            }
        }

        return $menu;
    }

    private function createRestaurant(Entity\Address $address, TaxCategoryInterface $taxCategory)
    {
        $contract = new Entity\Contract();
        $contract->setMinimumCartAmount(15);
        $contract->setFlatDeliveryPrice(7);

        $restaurant = new Entity\Restaurant();

        $restaurant->setAddress($address);
        $restaurant->setMenu($this->createMenu($taxCategory));
        $restaurant->setName($this->faker->restaurantName);
        $restaurant->addOpeningHour('Mo-Sa 10:00-19:00');
        $restaurant->setContract($contract);

        return $restaurant;
    }

    private function createRestaurants(OutputInterface $output)
    {
        foreach (['Entrées', 'Plats', 'Desserts'] as $name) {
            $this->createMenuCategory($name);
        }
        $this->doctrine->getManagerForClass(Entity\Menu\MenuCategory::class)->flush();

        $taxCategory = $this->createTaxCategory();

        for ($i = 0; $i < 100; $i++) {
            $restaurant = $this->createRestaurant($this->faker->randomAddress, $taxCategory);
            $this->doctrine->getManagerForClass(Entity\Restaurant::class)->persist($restaurant);
            $this->doctrine->getManagerForClass(Entity\Restaurant::class)->flush();

            $username = "resto-{$restaurant->getId()}";
            $user = $this->createUser($username, [
                'password' => $username,
                'roles' => ['ROLE_RESTAURANT']
            ]);
            $user->addRestaurant($restaurant);
        }

        $this->doctrine->getManagerForClass(Entity\Restaurant::class)->flush();
    }
}
