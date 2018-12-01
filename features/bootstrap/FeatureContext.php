<?php

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Order;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Address;
use AppBundle\Entity\DeliveryAddress;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Store\Token as StoreToken;
use AppBundle\Entity\Task;
use AppBundle\Sylius\Order\OrderInterface;
use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Coduo\PHPMatcher\Factory\SimpleFactory;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\Tools\SchemaTool;
use Behatch\HttpCall\HttpCallResultPool;
use PHPUnit\Framework\Assert;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Carbon\Carbon;
use libphonenumber\PhoneNumberUtil;
use Fidry\AliceDataFixtures\LoaderInterface;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context, SnippetAcceptingContext, KernelAwareContext
{
    /**
     * @var ManagerRegistry
     */
    private $doctrine;

    /**
     * @var \Doctrine\Common\Persistence\ObjectManager
     */
    private $manager;

    /**
     * @var SchemaTool
     */
    private $schemaTool;

    /**
     * @var array
     */
    private $classes;

    private $httpCallResultPool;

    private $kernel;

    private $restContext;

    private $tokens;

    private $fixturesLoader;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct(
        ManagerRegistry $doctrine,
        HttpCallResultPool $httpCallResultPool,
        PhoneNumberUtil $phoneNumberUtil,
        LoaderInterface $fixturesLoader)
    {
        $this->tokens = [];
        $this->doctrine = $doctrine;
        $this->manager = $doctrine->getManager();
        $this->schemaTool = new SchemaTool($this->manager);
        $this->classes = $this->manager->getMetadataFactory()->getAllMetadata();
        $this->httpCallResultPool = $httpCallResultPool;
        $this->phoneNumberUtil = $phoneNumberUtil;
        $this->fixturesLoader = $fixturesLoader;
    }

    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    protected function getContainer()
    {
        return $this->kernel->getContainer();
    }

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope)
    {
        $environment = $scope->getEnvironment();

        $this->restContext = $environment->getContext('Behatch\Context\RestContext');
        $this->minkContext = $environment->getContext('Behat\MinkExtension\Context\MinkContext');
    }

    private function getSession()
    {
        return $this->minkContext->getSession();
    }

    /**
     * @BeforeScenario
     */
    public function clearData()
    {
        $purger = new ORMPurger($this->doctrine->getManager());
        $purger->purge();
    }

    /**
     * @BeforeScenario
     */
    public function resetSequences()
    {
        $connection = $this->doctrine->getConnection();
        $rows = $connection->fetchAll('SELECT sequence_name FROM information_schema.sequences');
        foreach ($rows as $row) {
            $connection->executeQuery(sprintf('ALTER SEQUENCE %s RESTART WITH 1', $row['sequence_name']));
        }
    }

    /**
     * @BeforeScenario
     */
    public function clearAuthentication()
    {
        $this->tokens = [];
    }

    /**
     * @AfterScenario
     */
    public function unSetCarbon()
    {
        Carbon::setTestNow();
    }

    /**
     * @BeforeScenario @javascript
     */
    public function setGoogleApiKeySetting()
    {
        // TODO Verify the environment variable exists
        $googleApiKey = getenv('GOOGLE_API_KEY');

        $settingsManager = $this->getContainer()->get('coopcycle.settings_manager');
        $settingsManager->set('google_api_key', $googleApiKey);
    }

    /**
     * @Given the fixtures file :filename is loaded
     */
    public function theFixturesFileIsLoaded($filename)
    {
        $this->fixturesLoader->load([
            __DIR__.'/../fixtures/ORM/'.$filename
        ]);
    }

    /**
     * @Given the fixtures files are loaded:
     */
    public function theFixturesFilesAreLoaded(TableNode $table)
    {
        $filenames = array_map(function (array $row) {
            return __DIR__.'/../fixtures/ORM/'.current($row);
        }, $table->getRows());

        $this->fixturesLoader->load($filenames);
    }

    /**
     * @Given the redis database is empty
     */
    public function theRedisDatabaseIsEmpty()
    {
        $redis = $this->getContainer()->get('snc_redis.default');
        foreach ($redis->keys('*') as $key) {
            $redis->del($key);
        }
    }

    /**
     * @Given the current time is :datetime
     */
    public function currentTimeIs(string $datetime) {
        $now = new Carbon($datetime);
        Carbon::setTestNow($now);
    }

    /**
     * @Then the JSON should match:
     */
    public function theJsonShouldMatch(PyStringNode $string)
    {
        $expectedJson = $string->getRaw();
        $responseJson = $this->httpCallResultPool->getResult()->getValue();

        if (null === $expectedJson) {
            throw new \RuntimeException("Can not convert given JSON string to valid JSON format.");
        }

        $factory = new SimpleFactory();
        $matcher = $factory->createMatcher();
        $match = $matcher->match($responseJson, $expectedJson);

        if ($match !== true) {
            throw new \RuntimeException("Expected JSON doesn't match response JSON.");
        }
    }

    private function createUser($username, $email, $password, array $data = [])
    {
        $manipulator = $this->getContainer()->get('fos_user.util.user_manipulator');
        $manager = $this->getContainer()->get('fos_user.user_manager');

        if (!$user = $manager->findUserByUsername($username)) {
            $user = $manipulator->create($username, $password, $email, true, false);
        }

        $needsUpdate = false;

        if (isset($data['telephone'])) {
            $phoneNumber = $this->phoneNumberUtil->parse($data['telephone'], 'FR');
            $user->setTelephone($phoneNumber);
            $needsUpdate = true;
        }

        if (isset($data['givenName'])) {
            $user->setGivenName($data['givenName']);
            $needsUpdate = true;
        }

        if (isset($data['familyName'])) {
            $user->setFamilyName($data['familyName']);
            $needsUpdate = true;
        }

        if ($needsUpdate) {
            $manager->updateUser($user);
        }
    }

    /**
     * @Given the user is loaded:
     */
    public function theUserIsLoaded(TableNode $table)
    {
        $data = $table->getRowsHash();

        $this->createUser($data['username'], $data['email'], $data['password'], $data);
    }

    /**
     * @Given the user :username is loaded:
     */
    public function theUserWithUsernameIsLoaded($username, TableNode $table)
    {
        $data = $table->getRowsHash();

        $this->createUser($username, $data['email'], $data['password'], $data);
    }

    /**
     * @Given the courier is loaded:
     */
    public function theCourierIsLoaded(TableNode $table)
    {
        $userManager = $this->getContainer()->get('fos_user.user_manager');

        $data = $table->getRowsHash();

        $this->theUserIsLoaded($table);

        $user = $userManager->findUserByUsername($data['username']);
        $user->addRole('ROLE_COURIER');

        $userManager->updateUser($user);
    }

    /**
     * @Given the user :username has role :role
     */
    public function theUserHasRole($username, $role)
    {
        $userManager = $this->getContainer()->get('fos_user.user_manager');

        $user = $userManager->findUserByUsername($username);
        $user->addRole($role);

        $userManager->updateUser($user);
    }

    /**
     * @Given the courier :username is loaded:
     */
    public function theCourierWithUsernameIsLoaded($username, TableNode $table)
    {
        $userManager = $this->getContainer()->get('fos_user.user_manager');

        $data = $table->getRowsHash();

        $this->theUserWithUsernameIsLoaded($username, $table);

        $user = $userManager->findUserByUsername($username);
        $user->addRole('ROLE_COURIER');

        $userManager->updateUser($user);
    }

    /**
     * @Given the user :username has delivery address:
     */
    public function theUserHasDeliveryAddress($username, TableNode $table)
    {
        $data = $table->getRowsHash();

        $userManager = $this->getContainer()->get('fos_user.user_manager');
        $em = $this->doctrine->getManagerForClass('AppBundle:Address');

        $user = $userManager->findUserByUsername($username);

        list($lat, $lng) = explode(',', $data['geo']);

        $address = new Address();
        $address->setPostalCode(isset($data['streetAddress']) ? $data['streetAddress'] : 75000);
        $address->setAddressLocality('New-York');
        $address->setStreetAddress($data['streetAddress']);
        $address->setGeo(new GeoCoordinates(trim($lat), trim($lng)));

        $user->addAddress($address);

        $em->flush();
    }

    /**
     * @Given the user :username is authenticated
     */
    public function theUserIsAuthenticated($username)
    {
        $userManager = $this->getContainer()->get('fos_user.user_manager');
        $jwtManager = $this->getContainer()->get('lexik_jwt_authentication.jwt_manager');

        $user = $userManager->findUserByUsername($username);
        $token = $jwtManager->create($user);

        $this->tokens[$username] = $token;
    }

    /**
     * @When I send an authenticated :method request to :url
     */
    public function iSendAnAuthenticatedRequestTo($method, $url, PyStringNode $body = null)
    {
        $this->restContext->iAddHeaderEqualTo('Authorization', 'Bearer ' . $this->jwt);
        $this->restContext->iSendARequestTo($method, $url, $body);
    }

    /**
     * @When I send an authenticated :method request to :url with body:
     */
    public function iSendAnAuthenticatedRequestToWithBody($method, $url, PyStringNode $body)
    {
        $this->restContext->iAddHeaderEqualTo('Authorization', 'Bearer ' . $this->jwt);
        $this->restContext->iSendARequestTo($method, $url, $body);
    }

    /**
     * @When the user :username sends a :method request to :url
     */
    public function theUserSendsARequestTo($username, $method, $url)
    {
        if (!isset($this->tokens[$username])) {
            throw new \RuntimeException("User {$username} is not authenticated");
        }

        $this->restContext->iAddHeaderEqualTo('Authorization', 'Bearer ' . $this->tokens[$username]);
        $this->restContext->iSendARequestTo($method, $url);
    }

    /**
     * @When the user :username sends a :method request to :url with body:
     */
    public function theUserSendsARequestToWithBody($username, $method, $url, PyStringNode $body)
    {
        if (!isset($this->tokens[$username])) {
            throw new \RuntimeException("User {$username} is not authenticated");
        }

        $this->restContext->iAddHeaderEqualTo('Authorization', 'Bearer ' . $this->tokens[$username]);
        $this->restContext->iSendARequestTo($method, $url, $body);
    }

    /**
     * @Given the last delivery from user :username has status :status
     */
    public function theLastDeliveryFromUserHasStatus($username, $status)
    {
        $userManager = $this->getContainer()->get('fos_user.user_manager');

        $user = $userManager->findUserByUsername($username);

        $order = $this->doctrine->getRepository(Order::class)
            ->findOneBy(['customer' => $user], ['createdAt' => 'DESC']);

        $order->getDelivery()->setStatus($status);

        $this->doctrine->getManagerForClass(Delivery::class)->flush();
    }

    /**
     * @Given the last delivery from user :customer is dispatched to courier :courier
     */
    public function theLastDeliveryFromUserIsDispatchedToCourier($customerUsername, $courierUsername)
    {
        $userManager = $this->getContainer()->get('fos_user.user_manager');

        $customer = $userManager->findUserByUsername($customerUsername);
        $courier = $userManager->findUserByUsername($courierUsername);

        $order = $this->doctrine->getRepository(Order::class)
            ->findOneBy(['customer' => $customer], ['createdAt' => 'DESC']);

        $order->getDelivery()->setCourier($courier);
        $order->getDelivery()->setStatus(Delivery::STATUS_DISPATCHED);

        $this->doctrine->getManagerForClass(Delivery::class)->flush();
    }

    /**
     * @Given the tasks with comments matching :comments are assigned to :username
     */
    public function theTaskWithCommentsMatchingAreAssignedTo($comments, $username)
    {
        $userManager = $this->getContainer()->get('fos_user.user_manager');

        $user = $userManager->findUserByUsername($username);
        $qb = $this->doctrine
            ->getRepository(Task::class)
            ->createQueryBuilder('t')
            ->where('t.comments LIKE :comments')
            ->setParameter('comments', "%{$comments}%");

        $tasks = $qb->getQuery()->getResult();

        foreach ($tasks as $task) {
            $task->assignTo($user);
        }

        $this->doctrine->getManagerForClass(Task::class)->flush();
    }

    /**
     * @Given the setting :name has value :value
     */
    public function theSettingHasValue($name, $value)
    {
        $settingsManager = $this->getContainer()->get('coopcycle.settings_manager');

        $settingsManager->set($name, $value);
        $settingsManager->flush();
    }

    /**
     * @Given the restaurant with id :id has products:
     */
    public function theRestaurantWithIdHasProducts($id, TableNode $table)
    {
        $restaurant = $this->doctrine->getRepository(Restaurant::class)->find($id);

        $productCodes = array_map(function ($row) {
            return $row['code'];
        }, $table->getColumnsHash());

        foreach ($productCodes as $productCode) {
            $product = $this->getContainer()->get('sylius.repository.product')->findOneByCode($productCode);
            $restaurant->addProduct($product);
        }

        $this->doctrine->getManagerForClass(Restaurant::class)->flush();
    }

    /**
     * @Given the restaurant with id :id has menu:
     */
    public function theRestaurantWithIdHasMenu($id, TableNode $table)
    {
        $restaurant = $this->doctrine->getRepository(Restaurant::class)->find($id);

        $menu = $this->getContainer()->get('sylius.factory.taxon')->createNew();
        $menu->setCode(Uuid::uuid4()->toString());
        $menu->setSlug(Uuid::uuid4()->toString());
        $menu->setName('Menu');

        $children = array_map(function ($row) {

            $section = $this->getContainer()->get('sylius.factory.taxon')->createNew();
            $section->setCode(Uuid::uuid4()->toString());
            $section->setSlug(Uuid::uuid4()->toString());
            $section->setName($row['section']);

            $product = $this->getContainer()->get('sylius.repository.product')->findOneByCode($row['product']);

            $section->addProduct($product);

            return $section;

        }, $table->getColumnsHash());

        foreach ($children as $child) {
            $menu->addChild($child);
        }

        $restaurant->addTaxon($menu);
        $restaurant->setMenuTaxon($menu);

        $this->doctrine->getManagerForClass(Restaurant::class)->flush();
    }

    /**
     * @Given the store with name :name is authenticated as :username
     */
    public function theStoreWithNameIsAuthenticatedAs($name, $username)
    {
        $userManager = $this->getContainer()->get('fos_user.user_manager');
        $storeTokenManager = $this->getContainer()->get('coopcycle.store_token_manager');

        $store = $this->doctrine->getRepository(Store::class)->findOneByName($name);
        $user = $userManager->findUserByUsername($username);

        $token = $storeTokenManager->create($store, $user);

        $store->setToken($token);
        $this->doctrine->getManagerForClass(Store::class)->flush();

        $this->tokens[$username] = $token;
    }

    /**
     * @Given the store with name :name belongs to user :username
     */
    public function theStoreWithNameBelongsToUser($name, $username)
    {
        $userManager = $this->getContainer()->get('fos_user.user_manager');
        $user = $userManager->findUserByUsername($username);

        $store = $this->doctrine->getRepository(Store::class)->findOneByName($name);

        $user->addStore($store);
        $userManager->updateUser($user);
    }

    /**
     * @Given the restaurant with id :id belongs to user :username
     */
    public function theRestaurantWithIdBelongsToUser($id, $username)
    {
        $userManager = $this->getContainer()->get('fos_user.user_manager');
        $user = $userManager->findUserByUsername($username);

        $restaurant = $this->doctrine->getRepository(Restaurant::class)->find($id);

        $user->addRestaurant($restaurant);
        $userManager->updateUser($user);
    }

    /**
     * FIXME Too complicated, too low level
     */
    private function createRandomOrder(Restaurant $restaurant, UserInterface $user, \DateTime $shippedAt = null)
    {
        $orderTimelineCalculator = $this->getContainer()
            ->get('coopcycle.order_timeline_calculator');

        $order = $this->getContainer()->get('sylius.factory.order')
            ->createForRestaurant($restaurant);

        $order->setCustomer($user);

        if (null === $shippedAt) {
            // FIXME Using next opening date makes results change randomly
            $shippedAt = clone $restaurant->getNextOpeningDate();
            $shippedAt->modify('+30 minutes');
        }
        $order->setShippedAt($shippedAt);

        // FIXME Allow specifying an address in test
        $order->setShippingAddress($restaurant->getAddress());
        $order->setBillingAddress($restaurant->getAddress());

        $order->setTimeline($orderTimelineCalculator->calculate($order));

        foreach ($restaurant->getProducts() as $product) {

            $variant = $product->getVariants()->first();
            $item = $this->getContainer()->get('sylius.factory.order_item')->createNew();

            $item->setVariant($variant);
            $item->setUnitPrice($variant->getPrice());

            $this->getContainer()->get('sylius.order_item_quantity_modifier')->modify($item, 1);
            $this->getContainer()->get('sylius.order_modifier')->addToOrder($order, $item);
        }

        return $order;
    }

    /**
     * FIXME This assumes the order is in state "new"
     * @Given the user :username has ordered something at the restaurant with id :id
     */
    public function theUserHasOrderedSomethingAtTheRestaurantWithId($username, $id)
    {
        $userManager = $this->getContainer()->get('fos_user.user_manager');
        $user = $userManager->findUserByUsername($username);

        $restaurant = $this->doctrine->getRepository(Restaurant::class)->find($id);

        $order = $this->createRandomOrder($restaurant, $user);
        $order->setState(OrderInterface::STATE_NEW);

        $this->getContainer()->get('sylius.manager.order')->persist($order);
        $this->getContainer()->get('sylius.manager.order')->flush();
    }

    /**
     * @Given the user :username has ordered something for :date at the restaurant with id :id
     */
    public function theUserHasOrderedSomethingForAtRestaurantWithId($username, $date, $id)
    {
        $userManager = $this->getContainer()->get('fos_user.user_manager');
        $user = $userManager->findUserByUsername($username);

        $restaurant = $this->doctrine->getRepository(Restaurant::class)->find($id);

        $order = $this->createRandomOrder($restaurant, $user, new \DateTime($date));
        $order->setState(OrderInterface::STATE_NEW);

        $this->getContainer()->get('sylius.manager.order')->persist($order);
        $this->getContainer()->get('sylius.manager.order')->flush();
    }

    /**
     * @Then the last order from :username should be in state :state
     */
    public function theLastOrderFromShouldBeInState($username, $state)
    {
        $userManager = $this->getContainer()->get('fos_user.user_manager');
        $user = $userManager->findUserByUsername($username);

        $orderRepository = $this->getContainer()->get('sylius.repository.order');

        $order = $orderRepository->createQueryBuilder('o')
            ->andWhere('o.customer = :customer')
            ->setParameter('customer', $user)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        Assert::assertNotNull($order);
        Assert::assertEquals($state, $order->getState());
    }

    /**
     * @Then the restaurant with id :id should have :count closing rule
     * @Then the restaurant with id :id should have :count closing rules
     */
    public function theRestaurantWithIdShouldHaveClosingRules($id, $count)
    {
        $restaurant = $this->doctrine->getRepository(Restaurant::class)->find($id);

        Assert::assertEquals($count, count($restaurant->getClosingRules()));
    }

    private function findVisibleProductModal()
    {
        $session = $this->getSession();

        $modal = $session->getPage()->waitFor(10, function($page) {
            // FIXME The .modal selector is too general
            foreach ($page->findAll('css', '.modal') as $modal) {
                if ($modal->isVisible()) {
                    return $modal;
                }
            }

            return false;
        });

        return $modal;
    }

    /**
     * @Then the product options modal should appear
     */
    public function assertProductOptionsModalVisible()
    {
        $modal = $this->findVisibleProductModal();

        Assert::assertTrue($modal->isVisible());
    }

    /**
     * @Then the address modal should appear
     */
    public function assertAddressModalVisible()
    {
        $session = $this->getSession();

        $modal = $session->getPage()->waitFor(10, function($page) {

            return $page->find('css', '.ReactModal__Content--enter-address');
        });

        Assert::assertNotNull($modal);
        Assert::assertTrue($modal->isVisible());
    }

    /**
     * @Given I check all the mandatory product options
     */
    public function checkAllMandatoryProductOptions()
    {
        $modal = $this->findVisibleProductModal();

        Assert::assertTrue($modal->isVisible());

        $form = $modal->find('css', 'form');

        $listGroups = $form->findAll('css', '.list-group');

        foreach ($listGroups as $listGroup) {
            $optionName = $listGroup->find('css', 'input[type="radio"]')->getAttribute('name');

            $optionValues = [];
            foreach ($listGroup->findAll('css', 'input[type="radio"]') as $radio) {
                $optionValues[] = $radio->getAttribute('value');
            }

            $listGroup->selectFieldOption($optionName, current($optionValues));
        }
    }

    /**
     * @Given I submit the product options modal
     */
    public function submitProductOptionsModal()
    {
        $modal = $this->findVisibleProductModal();

        $modal->find('css', 'form button[type="submit"]')->press();
    }

    /**
     * @Then a product should be added to the cart
     */
    public function assertProductAddedToCart()
    {
        $session = $this->getSession();

        $cart = $session->getPage()->find('css', '#cart');

        $cartItem = $cart->waitFor(10, function($cart) {

            $cartItems = $cart->findAll('css', '.cart__items .cart__item');

            return count($cartItems) > 0 ? current($cartItems) : false;
        });

        Assert::assertNotNull($cartItem);
    }

    /**
     * @Given I click on restaurant :name
     */
    public function clickRestaurant($name)
    {
        $session = $this->getSession();

        $restaurants = $session->getPage()->findAll('css', '.restaurant-list .restaurant-item');

        foreach ($restaurants as $restaurant) {
            $restaurantName = $restaurant->find('css', '.caption > h4')->getText();

            if ($restaurantName === $name) {
                $restaurant->click();
                return;
            }
        }

        throw new \Exception(sprintf('Restaurant with name "%s" was not found in page', $name));
    }

    /**
     * @Given I click on menu item :name
     */
    public function clickMenuItem($name)
    {
        $session = $this->getSession();

        $menuItems = $session->getPage()->findAll('css', '.menu-item');

        foreach ($menuItems as $menuItem) {
            $menuItemName = $menuItem->find('css', '.menu-item-content')->getText();

            if ($menuItemName === $name) {
                $menuItem->click();
                return;
            }
        }

        throw new \Exception(sprintf('Menu item with name "%s" was not found in page', $name));
    }

    /**
     * @Then the product options modal submit button should not be disabled
     */
    public function assertProductOptionsModalSubmitButtonNotDisabled()
    {
        $modal = $this->findVisibleProductModal();

        Assert::assertTrue($modal->isVisible());

        $button = $modal->find('css', 'form button[type="submit"]');

        Assert::assertFalse($button->hasAttribute('disabled'));
    }

    /**
     * @Given I enter address :address in the address modal
     */
    public function enterAddressInModal($address)
    {
        $session = $this->getSession();

        $modal = $session->getPage()->waitFor(10, function($page) {

            return $page->find('css', '.ReactModal__Content--enter-address');
        });

        Assert::assertNotNull($modal);
        Assert::assertTrue($modal->isVisible());

        // We can't use setValue for autocomplete because we lose focus
        // Instead, we use low level method postValue
        // @see https://github.com/Behat/MinkExtension/issues/257

        $xpath = $this->getSession()->getSelectorsHandler()
            ->selectorToXpath('css', '.ReactModal__Content--enter-address input[type="text"]');

        $this->getSession()
            ->getDriver()
            ->getWebDriverSession()
            ->element('xpath', $xpath)
            ->postValue(['value' => [$address]]);
    }

    /**
     * @Then I should see address suggestions in the address modal
     */
    public function assertAddressSuggestionsInModal()
    {
        $session = $this->getSession();

        $addressPicker = $session->getPage()->find('css', '.ReactModal__Content--enter-address .autocomplete-wrapper');

        $suggestions = $addressPicker->waitFor(10, function($addressPicker) {

            $suggestions = $addressPicker->findAll('css', '.autocomplete-suggestions-wrapper .location-result');

            return count($suggestions) > 0 ? $suggestions : false;
        });

        Assert::assertNotCount(0, $suggestions);
    }

    /**
     * @Given I select the first address suggestion in the address modal
     */
    public function selectFirstAddressSuggestionInModal()
    {
        $session = $this->getSession();

        $addressPicker = $session->getPage()->find('css', '.ReactModal__Content--enter-address .autocomplete-wrapper');

        $suggestions = $addressPicker->waitFor(10, function($addressPicker) {

            $suggestions = $addressPicker->findAll('css', '.autocomplete-suggestions-wrapper .location-result');

            return count($suggestions) > 0 ? $suggestions : false;
        });

        Assert::assertNotCount(0, $suggestions);

        // FIXME Use :nth-child selector to be more strict
        $firstSuggestion = current($suggestions);

        $firstSuggestion->click();
    }

    /**
     * @Then the address modal should disappear
     */
    public function assertAddressModalNotVisible()
    {
        $session = $this->getSession();

        $timeout = 5;

        $isHidden = $session->getPage()->waitFor($timeout, function($page) {

            $modal = $page->find('css', '.ReactModal__Content--enter-address');

            return null === $modal;
        });

        Assert::assertTrue($isHidden, sprintf('Address modal is still visible after %d seconds', $timeout));
    }

    /**
     * @Then the cart address picker should contain :value
     */
    public function assertCartAddressPickerHasValue($value)
    {
        $session = $this->getSession();

        $cart = $session->getPage()->find('css', '#cart');
        $addressPicker = $cart->find('css', '.autocomplete-wrapper');
        $addressPickerInput = $addressPicker->find('css', 'input[type="text"]');

        Assert::assertEquals($value, $addressPickerInput->getValue());
    }
}
