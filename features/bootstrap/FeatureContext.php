<?php

use AppBundle\Entity\ApiApp;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Order;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Address;
use AppBundle\Entity\DeliveryAddress;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Store\Token as StoreToken;
use AppBundle\Entity\Task;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTimelineCalculator;
use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Coduo\PHPMatcher\Factory\SimpleFactory;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\Tools\SchemaTool;
use FOS\UserBundle\Util\UserManipulator;
use Behatch\HttpCall\HttpCallResultPool;
use PHPUnit\Framework\Assert;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Carbon\Carbon;
use libphonenumber\PhoneNumberUtil;
use Fidry\AliceDataFixtures\LoaderInterface;
use Trikoder\Bundle\OAuth2Bundle\Model\Client as OAuthClient;
use Trikoder\Bundle\OAuth2Bundle\Model\Grant;
use Trikoder\Bundle\OAuth2Bundle\Model\Scope;
use Trikoder\Bundle\OAuth2Bundle\OAuth2Grants;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Response;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Component\Process\Process;
use Symfony\Component\Panther\ProcessManager\WebServerReadinessProbeTrait;
use DMore\ChromeDriver\ChromeDriver;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context, SnippetAcceptingContext, KernelAwareContext
{
    use WebServerReadinessProbeTrait;

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

    private $oAuthTokens;

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
        LoaderInterface $fixturesLoader,
        SettingsManager $settingsManager,
        OrderTimelineCalculator $orderTimelineCalculator,
        UserManipulator $userManipulator,
        AuthorizationServer $authorizationServer)
    {
        $this->tokens = [];
        $this->oAuthTokens = [];
        $this->doctrine = $doctrine;
        $this->manager = $doctrine->getManager();
        $this->schemaTool = new SchemaTool($this->manager);
        $this->classes = $this->manager->getMetadataFactory()->getAllMetadata();
        $this->httpCallResultPool = $httpCallResultPool;
        $this->phoneNumberUtil = $phoneNumberUtil;
        $this->fixturesLoader = $fixturesLoader;
        $this->settingsManager = $settingsManager;
        $this->orderTimelineCalculator = $orderTimelineCalculator;
        $this->userManipulator = $userManipulator;
        $this->authorizationServer = $authorizationServer;
    }

    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    protected function getContainer()
    {
        return $this->kernel->getContainer();
    }

    private function isChromeRunning()
    {
        try {
            $client = HttpClient::create(['timeout' => 15]);
            $response = $client->request('GET', 'http://127.0.0.1:9222/json');

            return 200 === $response->getStatusCode();
        } catch (TransportException $e) {}

        return false;
    }

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope)
    {
        $environment = $scope->getEnvironment();

        $this->restContext = $environment->getContext('Behatch\Context\RestContext');
        $this->minkContext = $environment->getContext('Behat\MinkExtension\Context\MinkContext');

        // @see https://github.com/minkphp/Mink/commit/acf5fb1ec70b7de4902daf75301356702a26e6da
        // @see https://github.com/minkphp/Mink/issues/731
        if (!$this->minkContext->getSession()->isStarted()) {

            $driver = $this->minkContext->getSession()->getDriver();

            if ($driver instanceof ChromeDriver) {

                // If Chrome is not running, launch it
                if (!$this->isChromeRunning()) {
                    $this->process = new Process([
                        '/usr/bin/chromium-browser',
                        '--disable-gpu',
                        '--headless',
                        '--remote-debugging-address=0.0.0.0',
                        '--remote-debugging-port=9222',
                        '--no-sandbox',
                        // Fix Blocked a frame with origin "X" from accessing a cross-origin frame
                        // @see https://www.chromium.org/Home/chromium-security/site-isolation
                        '--disable-web-security',
                        '--disable-site-isolation-trials',
                    ]);

                    if (!$this->process->isRunning()) {
                        $this->checkPortAvailable('127.0.0.1', 9222);
                        $this->process->start();
                        $this->waitUntilReady($this->process, 'http://127.0.0.1:9222/json', false, 15);
                    }
                }
            }

            $this->minkContext->getSession()->start();
        }
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
        $this->oAuthTokens = [];
    }

    /**
     * @BeforeScenario
     */
    public function createChannels()
    {
        $this->theFixturesFileIsLoaded('sylius_channels.yml');
    }

    /**
     * @AfterScenario
     */
    public function unSetCarbon()
    {
        Carbon::setTestNow();

        $redis = $this->getContainer()->get('snc_redis.default');
        $redis->del('datetime:now');
    }

    /**
     * @BeforeScenario @javascript
     */
    public function setGoogleApiKeySetting()
    {
        // TODO Verify the environment variable exists
        $googleApiKey = getenv('GOOGLE_API_KEY');

        $this->settingsManager->set('google_api_key', $googleApiKey);
    }

    /**
     * @BeforeScenario @javascript
     */
    public function setStripeCredentialsSetting()
    {
        // TODO Verify the environment variables exist
        $stripePublishableKey = getenv('STRIPE_PUBLISHABLE_KEY');
        $stripeSecretKey = getenv('STRIPE_SECRET_KEY');

        $this->settingsManager->set('stripe_test_publishable_key', $stripePublishableKey);
        $this->settingsManager->set('stripe_test_secret_key', $stripeSecretKey);
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
    public function currentTimeIs(string $datetime)
    {
        Carbon::setTestNow(Carbon::parse($datetime));

        $redis = $this->getContainer()->get('snc_redis.default');
        $redis->set('datetime:now', Carbon::now()->toAtomString());
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
        $manager = $this->getContainer()->get('fos_user.user_manager');

        if (!$user = $manager->findUserByUsername($username)) {
            $enabled = isset($data['enabled']) ? filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN) : true;
            $user = $this->userManipulator->create($username, $password, $email, $enabled, false);
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

        if (isset($data['confirmationToken'])) {
            $user->setConfirmationToken($data['confirmationToken']);
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
        $em = $this->doctrine->getManagerForClass(Address::class);

        $user = $userManager->findUserByUsername($username);

        list($lat, $lng) = explode(',', $data['geo']);

        $address = new Address();
        $address->setStreetAddress($data['streetAddress']);
        $address->setPostalCode(isset($data['streetAddress']) ? $data['streetAddress'] : 75000);
        $address->setAddressLocality(isset($data['addressLocality']) ? $data['addressLocality'] : 'Paris');
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
     * @When the OAuth client :clientName sends a :method request to :url
     */
    public function theOAuthClientSendsARequestTo($clientName, $method, $url)
    {
        if (!isset($this->oAuthTokens[$clientName])) {
            throw new \RuntimeException("OAuth client {$clientName} is not authenticated");
        }

        $this->restContext->iAddHeaderEqualTo('Authorization', 'Bearer ' . $this->oAuthTokens[$clientName]);
        $this->restContext->iSendARequestTo($method, $url);
    }

    /**
     * @When the OAuth client :clientName sends a :method request to :url with body:
     */
    public function theOAuthClientSendsARequestToWithBody($clientName, $method, $url, PyStringNode $body)
    {
        if (!isset($this->oAuthTokens[$clientName])) {
            throw new \RuntimeException("OAuth client {$clientName} is not authenticated");
        }

        $this->restContext->iAddHeaderEqualTo('Authorization', 'Bearer ' . $this->oAuthTokens[$clientName]);
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
        $this->settingsManager->set($name, $value);
        $this->settingsManager->flush();
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

        $order->setTimeline($this->orderTimelineCalculator->calculate($order));

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
     * @Given I wait for cart to be ready
     */
    public function waitCartReady()
    {
        $session = $this->getSession();

        $cart = $session->getPage()->find('css', '#cart');

        $isReady = $cart->waitFor(30, function($cart) {

            if ($cart->getAttribute('data-ready') === 'true') {

                return true;
            }

            return false;
        });

        Assert::assertTrue($isReady);
    }

    /**
     * @Then the product options modal should appear
     */
    public function assertProductOptionsModalVisible()
    {
        $modal = $this->findVisibleProductModal();

        Assert::assertNotNull($modal);
        Assert::assertTrue($modal->isVisible());
    }

    /**
     * @Then the address modal should appear
     */
    public function assertAddressModalVisible()
    {
        $session = $this->getSession();

        $modal = $session->getPage()->waitFor(30, function($page) {

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
     * @Then the product :name should be added to the cart
     */
    public function assertProductAddedToCart($name)
    {
        $session = $this->getSession();

        $cart = $session->getPage()->find('css', '#cart');

        // FIXME For stability, we should check if loading has finished

        $cartItem = $cart->waitFor(30, function($cart) use ($name) {

            $cartItems = $cart->findAll('css', '.cart__items .cart__item');
            if (count($cartItems) === 0) {

                return false;
            }

            foreach ($cartItems as $cartItem) {
                $cartItemName = $cartItem->find('css', '.cart__item__content__body > span:first-of-type')->getText();
                if ($cartItemName === $name) {

                    return $cartItem;
                }
            }
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

        Assert::assertNotNull($modal);
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

        $addressPicker = $this->getSession()->getPage()->find('css', '.ReactModal__Content--enter-address input[type="text"]');
        $addressPicker->setValue($address);
    }

    /**
     * @Given I enter address :address in the homepage search
     */
    public function enterAddressInHomepageSearch($address)
    {
        $search = $this->getSession()->getPage()->find('css', '#address-search input[type="text"]');
        $search->setValue($address);
    }

    /**
     * @Then I should see address suggestions in the address modal
     */
    public function assertAddressSuggestionsInModal()
    {
        $session = $this->getSession();

        $addressPicker = $session->getPage()->find('css', '.ReactModal__Content--enter-address .address-autosuggest__container');

        $suggestions = $addressPicker->waitFor(10, function($addressPicker) {

            $suggestions = $addressPicker->findAll('css', '.react-autosuggest__suggestions-list .react-autosuggest__suggestion');

            return count($suggestions) > 0 ? $suggestions : false;
        });

        Assert::assertNotCount(0, $suggestions);
    }

    /**
     * @Then I should see address suggestions in the homepage search
     */
    public function assertAddressSuggestionsInHomepageSearch()
    {
        $session = $this->getSession();

        $addressPicker = $session->getPage()->find('css', '#address-search .address-autosuggest__container');

        $suggestions = $addressPicker->waitFor(10, function($addressPicker) {

            $suggestions = $addressPicker->findAll('css', '.react-autosuggest__suggestions-list .react-autosuggest__suggestion');

            return count($suggestions) > 0 ? $suggestions : false;
        });

        Assert::assertNotCount(0, $suggestions);
    }

    /**
     * @Then I should see section :title in the homepage search suggestions
     */
    public function assertSectionInHomepageSearch($title)
    {
        $session = $this->getSession();

        $addressPicker = $session->getPage()->find('css', '#address-search .address-autosuggest__container');

        $sectionTitles = $addressPicker->waitFor(10, function($addressPicker) {

            $sectionTitles =
                $addressPicker->findAll('css', '.react-autosuggest__suggestions-container .react-autosuggest__section-title');

            return count($sectionTitles) > 0 ? $sectionTitles : false;
        });

        Assert::assertNotCount(0, $sectionTitles);

        $sectionTitlesAsString = array_map(function ($sectionTitle) {

            return trim($sectionTitle->getText());
        }, $sectionTitles);

        Assert::assertContains($title, $sectionTitlesAsString);
    }

    /**
     * @Then I should see address :address in section :title in the homepage search suggestions
     */
    public function assertAddressInSectionInHomepageSearch($address, $title)
    {
        $session = $this->getSession();

        $addressPicker = $session->getPage()->find('css', '#address-search .address-autosuggest__container');

        $sectionContainer = $addressPicker->waitFor(10, function($addressPicker) use ($title) {

            $sectionContainers =
                $addressPicker->findAll('css', '.react-autosuggest__suggestions-container .react-autosuggest__section-container');

            foreach ($sectionContainers as $sectionContainer) {
                $sectionTitle = $sectionContainer->find('css', '.react-autosuggest__section-title');
                if (trim($sectionTitle->getText()) === $title) {

                    return $sectionContainer;
                }
            }

            return false;
        });

        Assert::assertNotNull($sectionContainer, sprintf('Section with title "%s" was not found on page', $title));

        $suggestions = $sectionContainer->findAll('css', '.react-autosuggest__suggestions-list .react-autosuggest__suggestion');

        Assert::assertNotCount(0, $suggestions);

        $suggestionsAsString = array_map(function ($suggestion) {

            return trim($suggestion->getText());
        }, $suggestions);

        Assert::assertContains($address, $suggestionsAsString);
    }

    /**
     * @Given I select address :address in section :title in the homepage search suggestions
     */
    public function selectAddressInSectionInHomepageSearch($address, $title)
    {
        $session = $this->getSession();

        $addressPicker = $session->getPage()->find('css', '#address-search .address-autosuggest__container');

        $sectionContainer = $addressPicker->waitFor(10, function($addressPicker) use ($title) {

            $sectionContainers =
                $addressPicker->findAll('css', '.react-autosuggest__suggestions-container .react-autosuggest__section-container');

            foreach ($sectionContainers as $sectionContainer) {
                $sectionTitle = $sectionContainer->find('css', '.react-autosuggest__section-title');
                if (trim($sectionTitle->getText()) === $title) {

                    return $sectionContainer;
                }
            }

            return false;
        });

        Assert::assertNotNull($sectionContainer, sprintf('Section with title "%s" was not found on page', $title));

        $suggestions = $sectionContainer->findAll('css', '.react-autosuggest__suggestions-list .react-autosuggest__suggestion');

        Assert::assertNotCount(0, $suggestions);

        $suggestionsAsString = array_map(function ($suggestion) {

            return trim($suggestion->getText());
        }, $suggestions);

        Assert::assertContains($address, $suggestionsAsString);

        foreach ($suggestions as $suggestion) {
            if (trim($suggestion->getText()) === $address) {
                $suggestion->click();
            }
        }
    }

    /**
     * @Given I select the first address suggestion in the address modal
     */
    public function selectFirstAddressSuggestionInModal()
    {
        $session = $this->getSession();

        $addressPicker = $session->getPage()->find('css', '.ReactModal__Content--enter-address .address-autosuggest__container');

        $suggestions = $addressPicker->waitFor(10, function($addressPicker) {

            $suggestions = $addressPicker->findAll('css', '.react-autosuggest__suggestions-list .react-autosuggest__suggestion');

            return count($suggestions) > 0 ? $suggestions : false;
        });

        Assert::assertNotCount(0, $suggestions);

        // FIXME Use :nth-child selector to be more strict
        $firstSuggestion = current($suggestions);

        $firstSuggestion->click();
    }

    /**
     * @Given I select the first address suggestion in the homepage search
     */
    public function selectFirstAddressSuggestionInHomepageSearch()
    {
        $session = $this->getSession();

        $addressPicker = $session->getPage()->find('css', '#address-search .address-autosuggest__container');

        $suggestions = $addressPicker->waitFor(10, function($addressPicker) {

            $suggestions = $addressPicker->findAll('css', '.react-autosuggest__suggestions-list .react-autosuggest__suggestion');

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
        $addressPicker = $cart->find('css', '.address-autosuggest__container');
        $addressPickerInput = $addressPicker->find('css', 'input[type="text"]');

        Assert::assertEquals($value, $addressPickerInput->getValue());
    }

    /**
     * @Then the cart submit button should not be disabled
     */
    public function assertCartSubmitButtonNotDisabled()
    {
        $timeout = 15;

        $isNotDisabled = $this->getSession()->getPage()->waitFor($timeout, function($page) {

            $button = $page->find('css', '#cart .panel-body button[type="submit"]');

            return false === $button->hasAttribute('disabled') && false === $button->hasClass('disabled');
        });

        Assert::assertTrue($isNotDisabled, sprintf('Submit button is still disabled after %d seconds', $timeout));
    }

    /**
     * @Given I submit the cart
     */
    public function submitCart()
    {
        $session = $this->getSession();

        $button = $session->getPage()->find('css', '#cart .panel-body button[type="submit"]');

        $button->click();
    }

    /**
     * @Given I login with username :username and password :password
     */
    public function loginWithUsernameAndPassword($username, $password)
    {
        $this->minkContext->fillField('username', $username);
        $this->minkContext->fillField('password', $password);
        $this->minkContext->pressButton('_submit');
    }

    /**
     * @Given I enter test credit card details
     */
    public function enterTestCreditCardDetails()
    {
        $session = $this->getSession();

        $iframe = $session->getPage()->waitFor(30, function($page) {

            return $page->find('css', '.StripeElement iframe');
        });

        Assert::assertNotNull($iframe, 'Stripe iframe was not found on page');

        $session->switchToIframe($iframe->getAttribute('name'));

        $cardNumber = $session->getPage()->find('css', 'input[name="cardnumber"]');
        $cardNumber->setValue('4242424242424242');

        $expMonth = date('m', strtotime('+1 month'));
        $expYear = date('y', strtotime('+1 month'));

        $expDate = $session->getPage()->find('css', 'input[name="exp-date"]');
        $expDate->setValue("{$expMonth}{$expYear}");

        $this->minkContext->fillField('cvc', '123');

        $session->switchToIframe();

        $session->getPage()->find('css', 'form[name="checkout_payment"] button[type="submit"]')->click();

        // FIXME There should be a better way
        $session->wait(5000);
    }

    /**
     * @Given I wait for url to match :regex
     */
    public function waitForURLToMatch($regex)
    {
        $addressMatches = $this->getSession()->getPage()->waitFor(30, function($page) use ($regex) {

            try {

                $this->minkContext->assertSession()->addressMatches(sprintf('#%s#', $regex));

                return true;

            } catch (ExpectationException $e) {
            } catch (\Exception $e) {
            }

            return false;
        });

        Assert::assertTrue($addressMatches);
    }

    /**
     * @Then the restaurant warning modal should appear
     */
    public function assertRestaurantWarningModalVisible()
    {
        $session = $this->getSession();

        $modal = $session->getPage()->waitFor(30, function($page) {

            return $page->find('css', '.ReactModal__Content--restaurant');
        });

        Assert::assertNotNull($modal);
        Assert::assertTrue($modal->isVisible());
    }

    /**
     * @Given the product with code :code is soft deleted
     */
    public function softDeleteProductWithCode($code)
    {
        $product = $this->getContainer()
            ->get('sylius.repository.product')
            ->findOneByCode($code);

        $em = $this->getContainer()->get('sylius.manager.product');

        $em->remove($product);
        $em->flush();
    }

    /**
     * @Given the store with name :storeName has an OAuth client named :clientName
     */
    public function createOauthClientForStore($storeName, $clientName)
    {
        $store = $this->doctrine->getRepository(Store::class)->findOneByName($storeName);

        $identifier = hash('md5', random_bytes(16));
        $secret = hash('sha512', random_bytes(32));

        $client = new OAuthClient($identifier, $secret);
        $client->setActive(true);

        $clientCredentials = new Grant(OAuth2Grants::CLIENT_CREDENTIALS);
        $client->setGrants($clientCredentials);

        $tasksScope = new Scope('tasks');
        $deliveriesScope = new Scope('deliveries');
        $client->setScopes($tasksScope, $deliveriesScope);

        $apiApp = new ApiApp();
        $apiApp->setOauth2Client($client);
        $apiApp->setName($clientName);
        $apiApp->setStore($store);

        $this->doctrine->getManagerForClass(ApiApp::class)->persist($apiApp);
        $this->doctrine->getManagerForClass(ApiApp::class)->flush();
    }

    /**
     * @Given the OAuth client with name :name has an access token
     */
    public function createAccessTokenForOauthClient($name)
    {
        $apiApp = $this->doctrine->getRepository(ApiApp::class)->findOneByName($name);

        $oAuthClient = $apiApp->getOauth2Client();

        $identifier = $oAuthClient->getIdentifier();
        $secret = $oAuthClient->getSecret();

        $headers = [
            'Authorization' => sprintf('Basic %s', base64_encode(sprintf('%s:%s', $identifier, $secret))),
        ];

        $body = [
            'grant_type' => 'client_credentials',
            'scope' => 'tasks deliveries'
        ];

        $request = new ServerRequest([], [], null, null, 'php://temp', $headers, [], [], $body);
        $response = new Response();

        $response = $this->authorizationServer->respondToAccessTokenRequest($request, $response);

        $data = json_decode($response->getBody(), true);

        $this->oAuthTokens[$name] = $data['access_token'];
    }
}
