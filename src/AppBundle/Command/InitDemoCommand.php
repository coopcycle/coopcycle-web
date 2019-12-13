<?php

namespace AppBundle\Command;

use AppBundle\Entity;
use AppBundle\Faker\AddressProvider;
use AppBundle\Faker\RestaurantProvider;
use AppBundle\Service\Geocoder;
use Craue\ConfigBundle\Util\Config;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Faker;
use Fidry\AliceDataFixtures\LoaderInterface;
use Fidry\AliceDataFixtures\Persistence\PurgeMode;
use FOS\UserBundle\Util\UserManipulator;
use libphonenumber\PhoneNumberUtil;
use Predis\Client as Redis;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\Factory as LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;

class InitDemoCommand extends Command
{
    private $userManipulator;
    private $doctrine;
    private $faker;
    private $fixturesLoader;
    private $client;
    private $redis;
    private $ormPurger;
    private $craueConfig;
    private $lockFactory;
    private $taxonFactory;
    private $phoneNumberUtil;
    private $batchSize = 10;
    private $excludedTables = [
        'craue_config_setting',
        'migration_versions',
    ];

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

    public function __construct(
        ManagerRegistry $doctrine,
        UserManipulator $userManipulator,
        LoaderInterface $fixturesLoader,
        Faker\Generator $faker,
        Redis $redis,
        Config $craueConfig,
        string $configEntityName,
        FactoryInterface $taxonFactory,
        PhoneNumberUtil $phoneNumberUtil,
        RepositoryInterface $taxCategoryRepository,
        FactoryInterface $taxCategoryFactory,
        ObjectManager $taxCategoryManager,
        FactoryInterface $taxRateFactory,
        ObjectManager $taxRateManager,
        Geocoder $geocoder)
    {
        $this->doctrine = $doctrine;
        $this->userManipulator = $userManipulator;
        $this->fixturesLoader = $fixturesLoader;
        $this->faker = $faker;
        $this->redis = $redis;
        $this->craueConfig = $craueConfig;
        $this->configEntityName = $configEntityName;
        $this->taxonFactory = $taxonFactory;
        $this->phoneNumberUtil = $phoneNumberUtil;
        $this->taxCategoryRepository = $taxCategoryRepository;
        $this->taxCategoryFactory = $taxCategoryFactory;
        $this->taxCategoryManager = $taxCategoryManager;
        $this->taxRateFactory = $taxRateFactory;
        $this->taxRateManager = $taxRateManager;
        $this->geocoder = $geocoder;

        parent::__construct();
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->faker->addProvider(new RestaurantProvider($this->faker));

        $this->ormPurger = new ORMPurger($this->doctrine->getManager(), $this->excludedTables);
        // $this->ormPurger->setPurgeMode(ORMPurger::PURGE_MODE_TRUNCATE);

        $store = new FlockStore();
        $this->lockFactory = new LockFactory($store);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $lock = $this->lockFactory->createLock('orm-purger');

        if ($lock->acquire()) {

            $output->writeln('Purging database…');
            $this->ormPurger->purge();

            $output->writeln('Resetting sequences…');
            $this->resetSequences();

            $output->writeln('Verifying database config…');
            $this->handleCraueConfig($input, $output);

            $output->writeln('Create lang...');
            $this->createLangs();

            $output->writeln('Creating super users…');
            foreach (self::$users as $username => $params) {
                $this->createUser($username, $params);
            }

            $output->writeln('Creating users…');
            for ($i = 1; $i <= 50; $i++) {
                $username = "user_{$i}";
                $user = $this->createUser($username, ['password' => $username]);
                $user->addAddress($this->faker->randomAddress);
            }
            $this->doctrine->getManagerForClass(Entity\ApiUser::class)->flush();

            $output->writeln('Creating couriers…');
            for ($i = 1; $i <= 50; $i++) {
                $this->createCourier("bot_{$i}");
            }

            $output->writeln('Creating restaurants…');
            $this->createRestaurants($output);

            $output->writeln('Creating stores…');
            $this->createStores();

            $output->writeln('Removing data from Redis…');
            $keys = $this->redis->keys('*');
            foreach ($keys as $key) {
                $this->redis->del($key);
            }

            $lock->release();
        }

        return 0;
    }

    private function resetSequences()
    {
        $connection = $this->doctrine->getConnection();
        $rows = $connection->fetchAll('SELECT sequence_name FROM information_schema.sequences');
        foreach ($rows as $row) {

            $sequenceName = $row['sequence_name'];
            $tableName = str_replace('_id_seq', '', $sequenceName);

            if (in_array($tableName, $this->excludedTables)) {
                continue;
            }

            try {
                $connection->executeQuery(sprintf('ALTER SEQUENCE %s RESTART WITH 1', $row['sequence_name']));
            } catch (TableNotFoundException $e) {
                // We don't care
            }
        }
    }

    private function loadFixtures($filename, array $objects = [])
    {
        return $this->fixturesLoader->load([$filename], $parameters = [], $objects, PurgeMode::createNoPurgeMode());
    }

    private function createLangs() {
        $en = new Locale();
        $en->setCode('en');
        $fr = new Locale();
        $fr->setCode('fr');
        $es = new Locale();
        $es->setCode('es');

        $em = $this->doctrine->getManagerForClass(Locale::class);
        $em->persist($en);
        $em->persist($es);
        $em->persist($fr);
        $em-> flush();
    }

    private function createCraueConfigSetting($name, $value, $section = 'general')
    {
        $className = $this->configEntityName;

        $setting = new $className();

        $setting->setName($name);
        $setting->setValue($value);
        $setting->setSection($section);

        return $setting;
    }

    private function handleCraueConfig(InputInterface $input, OutputInterface $output)
    {
        $className = $this->configEntityName;
        $em = $this->doctrine->getManagerForClass($className);

        try {
            $this->craueConfig->get('latlng');
        } catch (\RuntimeException $e) {
            $mapsCenter = $this->createCraueConfigSetting('latlng', '48.857498,2.335402');
            $em->persist($mapsCenter);
        }

        try {
            $this->craueConfig->get('brand_name');
        } catch (\RuntimeException $e) {
            $brandName = $this->createCraueConfigSetting('brand_name', 'CoopCycle');
            $em->persist($brandName);
        }

        $em->flush();

        $addressProvider = new AddressProvider($this->faker, $this->geocoder);

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
        if ($taxCategory = $this->taxCategoryRepository->findOneByCode($taxCategoryCode)) {
            return $taxCategory;
        }

        $taxCategory = $this->taxCategoryFactory->createNew();
        $taxCategory->setName($taxCategoryName);
        $taxCategory->setCode($taxCategoryCode);

        $this->taxCategoryManager->persist($taxCategory);
        $this->taxCategoryManager->flush();

        $taxRate = $this->taxRateFactory->createNew();
        $taxRate->setName($taxRateName);
        $taxRate->setCode($taxRateCode);
        $taxRate->setCategory($taxCategory);
        $taxRate->setAmount($taxRateAmount);
        $taxRate->setIncludedInPrice(true);
        $taxRate->setCalculator('default');

        $this->taxRateManager->persist($taxRate);
        $this->taxRateManager->flush();

        return $taxCategory;
    }

    private function createMenuTaxon($appetizers, $dishes, $desserts)
    {
        $menu = $this->taxonFactory->createNew();

        $menu->setCode($this->faker->uuid);
        $menu->setSlug($this->faker->uuid);
        $menu->setName('Default');

        $appetizersTaxon = $this->taxonFactory->createNew();
        $appetizersTaxon->setCode($this->faker->uuid);
        $appetizersTaxon->setSlug($this->faker->uuid);
        $appetizersTaxon->setName('Entrées');
        foreach ($appetizers as $product) {
            $appetizersTaxon->addProduct($product);
        }

        $dishesTaxon = $this->taxonFactory->createNew();
        $dishesTaxon->setCode($this->faker->uuid);
        $dishesTaxon->setSlug($this->faker->uuid);
        $dishesTaxon->setName('Plats');
        foreach ($dishes as $product) {
            $dishesTaxon->addProduct($product);
        }

        $dessertsTaxon = $this->taxonFactory->createNew();
        $dessertsTaxon->setCode($this->faker->uuid);
        $dessertsTaxon->setSlug($this->faker->uuid);
        $dessertsTaxon->setName('Desserts');
        foreach ($desserts as $product) {
            $dessertsTaxon->addProduct($product);
        }

        $menu->addChild($appetizersTaxon);
        $menu->addChild($dishesTaxon);
        $menu->addChild($dessertsTaxon);

        return $menu;
    }

    private function createAppetizers(TaxCategoryInterface $taxCategory)
    {
        $products = [];

        for ($i = 0; $i < 5; $i++) {
            $appetizer = $this->loadFixtures(__DIR__ . '/Resources/appetizer.yml', [
                'taxCategory' => $taxCategory,
            ]);

            $appetizer['variant']->setName($appetizer['product']->getName());

            $products[] = $appetizer['product'];
        }

        return $products;
    }

    private function createDishes(TaxCategoryInterface $taxCategory)
    {
        $products = [];

        $options = $this->loadFixtures(__DIR__ . '/Resources/product_options.yml');

        for ($i = 0; $i < 5; $i++) {

            $dish = $this->loadFixtures(__DIR__ . '/Resources/dish.yml', [
                'taxCategory' => $taxCategory,
            ]);

            $dish['variant']->setName($dish['product']->getName());

            $dish['product']->addOption($options['accompaniments']);
            $dish['product']->addOption($options['drinks']);

            $products[] = $dish['product'];
        }

        return $products;
    }

    private function createDesserts(TaxCategoryInterface $taxCategory)
    {
        $products = [];

        for ($i = 0; $i < 5; $i++) {
            $dessert = $this->loadFixtures(__DIR__ . '/Resources/dessert.yml', [
                'taxCategory' => $taxCategory,
            ]);

            $dessert['variant']->setName($dessert['product']->getName());

            $products[] = $dessert['product'];
        }

        return $products;
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
            array_push($prices, random_int(500, 2000));
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

        $phoneNumber = $this->phoneNumberUtil->parse($this->faker->phoneNumber, 'FR');

        $shop->setEnabled(true);
        $shop->setTelephone($phoneNumber);
        $shop->addAddress($address);
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
        $contract->setMinimumCartAmount(1500);
        $contract->setFlatDeliveryPrice(350);
        $contract->setCustomerAmount(350);
        $contract->setFeeRate(0);

        $restaurant = new Entity\Restaurant();

        $phoneNumber = $this->phoneNumberUtil->parse($this->faker->phoneNumber, 'FR');

        $restaurant->setEnabled(true);
        $restaurant->setTelephone($phoneNumber);
        $restaurant->setAddress($address);
        $restaurant->setName($this->faker->restaurantName);
        $restaurant->addOpeningHour('Mo-Fr ' . $this->createRandomTimeRange('09:30', '14:30'));
        $restaurant->addOpeningHour('Mo-Fr ' . $this->createRandomTimeRange('19:30', '23:30'));
        $restaurant->addOpeningHour('Sa-Su ' . $this->createRandomTimeRange('08:30', '15:30'));
        $restaurant->addOpeningHour('Sa-Su ' . $this->createRandomTimeRange('19:00', '01:30'));
        $restaurant->setContract($contract);

        $appetizers = $this->createAppetizers($taxCategory);
        $dishes = $this->createDishes($taxCategory);
        $desserts = $this->createDesserts($taxCategory);

        foreach ($appetizers as $product) {
            $restaurant->addProduct($product);
            foreach ($product->getOptions() as $productOption) {
                $restaurant->addProductOption($productOption);
            }
        }
        foreach ($dishes as $product) {
            $restaurant->addProduct($product);
            foreach ($product->getOptions() as $productOption) {
                $restaurant->addProductOption($productOption);
            }
        }
        foreach ($desserts as $product) {
            $restaurant->addProduct($product);
            foreach ($product->getOptions() as $productOption) {
                $restaurant->addProductOption($productOption);
            }
        }

        $menuTaxon = $this->createMenuTaxon($appetizers, $dishes, $desserts);

        $restaurant->addTaxon($menuTaxon);
        $restaurant->setMenuTaxon($menuTaxon);

        return $restaurant;
    }

    private function createRestaurants(OutputInterface $output)
    {
        $foodTaxCategory =
            $this->createTaxCategory('TVA consommation immédiate', 'tva_conso_immediate', 'TVA 10%', 'tva_10', 0.10);

        $this->createTaxCategory('TVA consommation différée', 'tva_conso_differee', 'TVA 5.5%', 'tva_5_5', 0.055);
        $this->createTaxCategory('TVA livraison', 'tva_livraison', 'TVA 20%', 'tva_20', 0.20);

        $em = $this->doctrine->getManagerForClass(Entity\Restaurant::class);

        for ($i = 0; $i < 50; $i++) {

            $restaurant = $this->createRestaurant($this->faker->randomAddress, $foodTaxCategory);

            $em->persist($restaurant);

            $username = "resto_{$i}";
            $user = $this->createUser($username, [
                'password' => $username,
                'roles' => ['ROLE_RESTAURANT']
            ]);
            $user->addRestaurant($restaurant);

            if (($i % $this->batchSize) === 0) {

                $output->writeln('Flushing data…');

                $em->flush();
                $em->clear();

                // As we have cleared the whole UnitOfWork, we need to restore the TaxCategory entity
                $foodTaxCategory = $this->taxCategoryRepository->findOneByCode('tva_conso_immediate');
            }
        }

        $em->flush();
    }

    private function createStores()
    {
        for ($i = 1; $i <= 25; $i++) {
            $store = $this->createStore($this->faker->randomAddress);
            $pricingRuleSet = $this->createPricingRuleSet($store);
            $store->setPricingRuleSet($pricingRuleSet);
            $this->doctrine->getManagerForClass(Entity\Store::class)->persist($store);
            $this->doctrine->getManagerForClass(Entity\Store::class)->flush();

            $username = "store_{$i}";
            $user = $this->createUser($username, [
                'password' => $username,
                'roles' => ['ROLE_STORE']
            ]);
            $user->addStore($store);
        }

        $this->doctrine->getManagerForClass(Entity\Store::class)->flush();
    }
}
