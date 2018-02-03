<?php

namespace AppBundle\Command;

use AppBundle\Entity;
use AppBundle\Faker\AddressProvider;
use AppBundle\Faker\RestaurantProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Faker;
use GuzzleHttp\Client;
use Nelmio\Alice\Fixtures\Loader as FixturesLoader;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;

class InitDemoCommand extends ContainerAwareCommand
{
    private $userManipulator;
    private $doctrine;
    private $faker;
    private $fixturesLoader;
    private $client;
    private $redis;
    private $ormPurger;
    private $craueConfig;

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

        $this->faker->addProvider($restaurantProvider);
        $this->fixturesLoader->addProvider($restaurantProvider);

        $this->redis = $this->getContainer()->get('snc_redis.default');

        $this->craueConfig = $this->getContainer()->get('craue_config');

        $this->ormPurger = new ORMPurger($this->getContainer()->get('doctrine')->getManager(), [
            'craue_config_setting',
            'migration_versions',
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Purging database...');
        $this->ormPurger->purge();

        $output->writeln('Verifying database config...');
        $this->handleCraueConfig($input, $output);

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

        $output->writeln('Creating stores...');
        $this->createStores();

        $output->writeln('Removing data from Redis...');
        $keys = $this->redis->keys('*');
        foreach ($keys as $key) {
            $this->redis->del($key);
        }
    }

    private function createCraueConfigSetting($name, $value, $section = 'general')
    {
        $className = $this->getContainer()->getParameter('craue_config.entity_name');

        $setting = new $className();

        $setting->setName($name);
        $setting->setValue($value);
        $setting->setSection($section);

        return $setting;
    }

    private function handleCraueConfig(InputInterface $input, OutputInterface $output)
    {
        $className = $this->getContainer()->getParameter('craue_config.entity_name');
        $em = $this->doctrine->getManagerForClass($className);

        try {
            $this->craueConfig->get('maps.center');
        } catch (\RuntimeException $e) {
            $mapsCenter = $this->createCraueConfigSetting('maps.center', '48.857498,2.335402');
            $em->persist($mapsCenter);
        }

        try {
            $this->craueConfig->get('google_api_key');
        } catch (\RuntimeException $e) {
            $question = new Question('Please enter a Google API key');
            $apiKey = $this->getHelper('question')->ask($input, $output, $question);
            if (!$apiKey) {
                throw new \Exception('No Google API key provided');
            }

            $googleApiKey = $this->createCraueConfigSetting('google_api_key', $apiKey);
            $em->persist($googleApiKey);
        }

        $em->flush();

        $client = new Client([
            'base_uri' => 'https://maps.googleapis.com',
            'timeout'  => 2.0,
        ]);

        $apiKey = $this->craueConfig->get('google_api_key');
        $addressProvider = new AddressProvider($this->faker, $client, $apiKey);

        $this->faker->addProvider($addressProvider);
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

    private function createTaxCategory($taxCategoryName, $taxCategoryCode, $taxRateName, $taxRateCode, $taxRateAmount)
    {
        $taxCategoryFactory = $this->getContainer()->get('sylius.factory.tax_category');
        $taxCategoryManager = $this->getContainer()->get('sylius.manager.tax_category');
        $taxRateFactory = $this->getContainer()->get('sylius.factory.tax_rate');
        $taxRateManager = $this->getContainer()->get('sylius.manager.tax_rate');

        $taxCategory = $taxCategoryFactory->createNew();
        $taxCategory->setName($taxCategoryName);
        $taxCategory->setCode($taxCategoryCode);

        $taxCategoryManager->persist($taxCategory);
        $taxCategoryManager->flush();

        $taxRate = $taxRateFactory->createNew();
        $taxRate->setName($taxRateName);
        $taxRate->setCode($taxRateCode);
        $taxRate->setCategory($taxCategory);
        $taxRate->setAmount($taxRateAmount);
        $taxRate->setIncludedInPrice(true);
        $taxRate->setCalculator('float');

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

    private function createRandomTimeRange($min, $max)
    {
        [$closingHour, $closingMinute] = explode(':', $max);
        [$openingHour, $openingMinute] = explode(':', $min);

        $closing = new \DateTime();
        $closing->setTime($closingHour, $closingMinute);

        $opening = new \DateTime();
        $opening->setTime($openingHour, $openingMinute);

        $increment = mt_rand(0, 5) * 15;
        $decrement = mt_rand(0, 5) * 15;

        $opening->modify("+{$increment} minutes");
        $closing->modify("-{$decrement} minutes");

        return sprintf('%s-%s', $opening->format('H:i'), $closing->format('H:i'));
    }

    private function createPricingRuleSet(Entity\Store $store)
    {
        $pricingRuleSet = new Entity\Delivery\PricingRuleSet();
        $pricingRuleSet->setName('Tarification de '.$store->getName());

        $distances = [];
        $prices = [];

        for ($i = 1; $i <= 6; $i++) {
            array_push($distances, random_int(1, 20) * 1000);
        }

        for ($i = 1; $i <= 5; $i++) {
            array_push($prices, random_int(5, 20));
        }

        sort($distances);
        sort($prices);

        foreach ($prices as $k => $price) {
            $pricingRule = new Entity\Delivery\PricingRule();
            $pricingRule->setPosition($k);
            $pricingRule->setExpression('distance in '.(string)$distances[$k].'..'.(string)$distances[$k+1]);
            $pricingRule->setPrice($price);
            $pricingRule->setRuleSet($pricingRuleSet);
            $pricingRuleSet->getRules()->add($pricingRule);
        };


        return $pricingRuleSet;
    }

    private function createStore(Entity\Address $address)
    {
        $shop = new Entity\Store();

        $shop->setEnabled(true);
        $shop->setTelephone('+33623456789');
        $shop->setAddress($address);
        $shop->setName($this->faker->storeName);
        $shop->addOpeningHour('Mo-Fr ' . $this->createRandomTimeRange('09:30', '14:30'));
        $shop->addOpeningHour('Mo-Fr ' . $this->createRandomTimeRange('19:30', '23:30'));
        $shop->addOpeningHour('Sa-Su ' . $this->createRandomTimeRange('08:30', '15:30'));
        $shop->addOpeningHour('Sa-Su ' . $this->createRandomTimeRange('19:00', '01:30'));

        return $shop;
    }

    private function createRestaurant(Entity\Address $address, TaxCategoryInterface $taxCategory)
    {
        $contract = new Entity\Contract();
        $contract->setMinimumCartAmount(15);
        $contract->setFlatDeliveryPrice(3.5);

        $restaurant = new Entity\Restaurant();

        $restaurant->setEnabled(true);
        $restaurant->setTelephone('+33623456789');
        $restaurant->setAddress($address);
        $restaurant->setMenu($this->createMenu($taxCategory));
        $restaurant->setName($this->faker->restaurantName);
        $restaurant->addOpeningHour('Mo-Fr ' . $this->createRandomTimeRange('09:30', '14:30'));
        $restaurant->addOpeningHour('Mo-Fr ' . $this->createRandomTimeRange('19:30', '23:30'));
        $restaurant->addOpeningHour('Sa-Su ' . $this->createRandomTimeRange('08:30', '15:30'));
        $restaurant->addOpeningHour('Sa-Su ' . $this->createRandomTimeRange('19:00', '01:30'));
        $restaurant->setContract($contract);

        return $restaurant;
    }

    private function createRestaurants(OutputInterface $output)
    {
        foreach (['Entrées', 'Plats', 'Desserts'] as $name) {
            $this->createMenuCategory($name);
        }
        $this->doctrine->getManagerForClass(Entity\Menu\MenuCategory::class)->flush();

        $foodTaxCategory =
            $this->createTaxCategory('TVA consommation immédiate', 'tva_conso_immediate', 'TVA 10%', 'tva_10', 0.10);

        $this->createTaxCategory('TVA consommation différée', 'tva_conso_differee', 'TVA 5.5%', 'tva_5_5', 0.055);
        $this->createTaxCategory('TVA livraison', 'tva_livraison', 'TVA 20%', 'tva_20', 0.20);

        for ($i = 0; $i < 100; $i++) {
            $restaurant = $this->createRestaurant($this->faker->randomAddress, $foodTaxCategory);
            $this->doctrine->getManagerForClass(Entity\Restaurant::class)->persist($restaurant);
            $this->doctrine->getManagerForClass(Entity\Restaurant::class)->flush();

            $username = "resto-{$i}";
            $user = $this->createUser($username, [
                'password' => $username,
                'roles' => ['ROLE_RESTAURANT']
            ]);
            $user->addRestaurant($restaurant);
        }

        $this->doctrine->getManagerForClass(Entity\Restaurant::class)->flush();
    }

    private function createStores()
    {

        for ($i = 0; $i < 25; $i++) {
            $store = $this->createStore($this->faker->randomAddress);
            $pricingRuleSet = $this->createPricingRuleSet($store);
            $store->setPricingRuleSet($pricingRuleSet);
            $this->doctrine->getManagerForClass(Entity\Store::class)->persist($store);
            $this->doctrine->getManagerForClass(Entity\Store::class)->flush();

            $username = "store-{$i}";
            $user = $this->createUser($username, [
                'password' => $username,
                'roles' => ['ROLE_STORE']
            ]);
            $user->addStore($store);
        }

        $this->doctrine->getManagerForClass(Entity\Store::class)->flush();
    }
}
