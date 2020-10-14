<?php

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\DataType\TsRange;
use AppBundle\Entity\ApiApp;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\ClosingRule;
use AppBundle\Entity\Order;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Address;
use AppBundle\Entity\DeliveryAddress;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Organization;
use AppBundle\Entity\RemotePushToken;
use AppBundle\Entity\Store;
use AppBundle\Entity\Store\Token as StoreToken;
use AppBundle\Entity\Task;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Utils\OrderTimelineCalculator;
use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Behat\Testwork\Tester\Result\TestResult;
use Behat\Behat\Tester\Exception\PendingException;
use Coduo\PHPMatcher\PHPMatcher;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\Tools\SchemaTool;
use Faker\Generator as FakerGenerator;
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
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use DMore\ChromeDriver\ChromeDriver;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
        AuthorizationServer $authorizationServer,
        Redis $redis,
        IriConverterInterface $iriConverter,
        HttpMessageFactoryInterface $httpMessageFactory,
        Redis $tile38,
        FakerGenerator $faker)
    {
        $this->tokens = [];
        $this->oAuthTokens = [];
        $this->doctrine = $doctrine;
        $this->manager = $doctrine->getManager();
        $this->schemaTool = new SchemaTool($this->manager);
        $this->httpCallResultPool = $httpCallResultPool;
        $this->phoneNumberUtil = $phoneNumberUtil;
        $this->fixturesLoader = $fixturesLoader;
        $this->settingsManager = $settingsManager;
        $this->orderTimelineCalculator = $orderTimelineCalculator;
        $this->userManipulator = $userManipulator;
        $this->authorizationServer = $authorizationServer;
        $this->redis = $redis;
        $this->iriConverter = $iriConverter;
        $this->httpMessageFactory = $httpMessageFactory;
        $this->tile38 = $tile38;
        $this->faker = $faker;
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

        $this->redis->del('datetime:now');
    }

    /**
     * @AfterScenario
     */
    public function disableMaintenance()
    {
        $this->redis->del('maintenance');
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
        foreach ($this->redis->keys('*') as $key) {
            $this->redis->del($key);
        }
    }

    /**
     * @Given the current time is :datetime
     */
    public function currentTimeIs(string $datetime)
    {
        Carbon::setTestNow(Carbon::parse($datetime));

        $this->redis->set('datetime:now', Carbon::now()->toAtomString());
    }

    /**
     * @Given the maintenance mode is on
     */
    public function enableMaintenance()
    {
        $this->redis->set('maintenance', '1');
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

        $matcher = new PHPMatcher();
        $match = $matcher->match($responseJson, $expectedJson);

        if ($match !== true) {
            throw new \RuntimeException(sprintf("Expected JSON doesn't match response JSON.\n%s",
                (string) $matcher->backtrace()));
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

        if (isset($data['passwordRequestAge'])) {
            $ageInSeconds = new DateInterval('PT'.$data['passwordRequestAge']."S");

            $timestamp = new DateTime();
            $timestamp->sub($ageInSeconds);

            $user->setPasswordRequestedAt($timestamp);
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
        $restaurant = $this->doctrine->getRepository(LocalBusiness::class)->find($id);

        $productCodes = array_map(function ($row) {
            return $row['code'];
        }, $table->getColumnsHash());

        foreach ($productCodes as $productCode) {
            $product = $this->getContainer()->get('sylius.repository.product')->findOneByCode($productCode);
            $restaurant->addProduct($product);
        }

        $this->doctrine->getManagerForClass(LocalBusiness::class)->flush();
    }

    /**
     * @Given the restaurant with id :id has menu:
     */
    public function theRestaurantWithIdHasMenu($id, TableNode $table)
    {
        $restaurant = $this->doctrine->getRepository(LocalBusiness::class)->find($id);

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

        $this->doctrine->getManagerForClass(LocalBusiness::class)->flush();
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

        $restaurant = $this->doctrine->getRepository(LocalBusiness::class)->find($id);

        $user->addRestaurant($restaurant);
        $userManager->updateUser($user);
    }

    /**
     * FIXME Too complicated, too low level
     */
    private function createRandomOrder(LocalBusiness $restaurant, UserInterface $user, \DateTime $shippedAt = null)
    {
        $order = $this->getContainer()->get('sylius.factory.order')
            ->createForRestaurant($restaurant);

        $order->setCustomer($user->getCustomer());

        if (null === $shippedAt) {
            // FIXME Using next opening date makes results change randomly
            $shippedAt = clone $restaurant->getNextOpeningDate();
            $shippedAt->modify('+30 minutes');
        }

        $rangeLower = clone $shippedAt;
        $rangeUpper = clone $shippedAt;

        $rangeLower->modify('-5 minutes');
        $rangeUpper->modify('+5 minutes');

        $range = new TsRange();
        $range->setLower($rangeLower);
        $range->setUpper($rangeUpper);

        $order->setShippingTimeRange($range);

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

        $restaurant = $this->doctrine->getRepository(LocalBusiness::class)->find($id);

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

        $restaurant = $this->doctrine->getRepository(LocalBusiness::class)->find($id);

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
        $restaurant = $this->doctrine->getRepository(LocalBusiness::class)->find($id);

        Assert::assertEquals($count, count($restaurant->getClosingRules()));
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

        $body = [
            'grant_type' => 'client_credentials',
            'scope' => 'tasks deliveries'
        ];

        $request = $this->httpMessageFactory->createRequest(
            Request::create('/uri', $method = 'POST', $parameters = $body, $cookies = [], $files = [], $server = [
                'HTTP_AUTHORIZATION' => sprintf('Basic %s', base64_encode(sprintf('%s:%s', $identifier, $secret))),
            ], $content = null)
        );
        $response = $this->httpMessageFactory->createResponse(new Response());

        $response = $this->authorizationServer->respondToAccessTokenRequest($request, $response);

        $data = json_decode($response->getBody(), true);

        $this->oAuthTokens[$name] = $data['access_token'];
    }

    /**
     * @Given the store with name :storeName has check expression :expression
     */
    public function storeHasCheckExpression($storeName, $expression)
    {
        $store = $this->doctrine->getRepository(Store::class)->findOneByName($storeName);

        $store->setCheckExpression($expression);

        $this->doctrine->getManagerForClass(Store::class)->flush();
    }

    /**
     * @Given the user :username has created a cart at restaurant with id :id
     */
    public function theUserHasCreatedACartAtRestaurantWithId($username, $id)
    {
        $userManager = $this->getContainer()->get('fos_user.user_manager');
        $user = $userManager->findUserByUsername($username);

        $restaurant = $this->doctrine->getRepository(LocalBusiness::class)->find($id);

        $cart = $this->getContainer()->get('sylius.factory.order')
            ->createForRestaurant($restaurant);

        $cart->setCustomer($user->getCustomer());

        $this->getContainer()->get('sylius.manager.order')->persist($cart);
        $this->getContainer()->get('sylius.manager.order')->flush();
    }

    /**
     * @Given there is a cart at restaurant with id :id
     */
    public function createCartAtRestaurantWithId($id)
    {
        $restaurant = $this->doctrine->getRepository(LocalBusiness::class)->find($id);

        $cart = $this->getContainer()->get('sylius.factory.order')
            ->createForRestaurant($restaurant);

        $this->getContainer()->get('sylius.manager.order')->persist($cart);
        $this->getContainer()->get('sylius.manager.order')->flush();

        return $cart;
    }

    private function getLastCartFromRestaurant(LocalBusiness $restaurant)
    {
        $carts = $this->getContainer()->get('sylius.repository.order')
            ->findCartsByRestaurant($restaurant);

        uasort($carts, function ($a, $b) {
            if ($a->getCreatedAt() === $b->getCreatedAt()) {
                return $a->getId() < $b->getId() ? -1 : 1;
            }
            return $a->getCreatedAt() < $b->getCreatedAt() ? -1 : 1;
        });

        return array_pop($carts);
    }

    /**
     * @Given there is a token for the last cart at restaurant with id :id
     */
    public function thereIsATokenForTheLastCartAtRestaurantWithId($id)
    {
        $restaurant = $this->doctrine->getRepository(LocalBusiness::class)->find($id);
        $cart = $this->getLastCartFromRestaurant($restaurant);

        $jwtEncoder = $this->getContainer()->get('lexik_jwt_authentication.encoder');

        $payload = [
            'sub' => $this->iriConverter->getIriFromItem($cart, \ApiPlatform\Core\Api\UrlGeneratorInterface::ABS_URL),
        ];
        $this->jwt = $jwtEncoder->encode($payload);
    }

    /**
     * @Given there is an expired token for the last cart at restaurant with id :id
     */
    public function thereIsAnExpiredTokenForTheLastCartAtRestaurantWithId($id)
    {
        $restaurant = $this->doctrine->getRepository(LocalBusiness::class)->find($id);
        $cart = $this->getLastCartFromRestaurant($restaurant);

        $jwtEncoder = $this->getContainer()->get('lexik_jwt_authentication.encoder');

        $payload = [
            'sub' => $this->iriConverter->getIriFromItem($cart, \ApiPlatform\Core\Api\UrlGeneratorInterface::ABS_URL),
            'exp' => time() - (60 * 60),
        ];
        $this->jwt = $jwtEncoder->encode($payload);
    }

    /**
     * @Given the client is authenticated with last response token
     */
    public function theClientIsAuthenticatedWithLastResponseToken()
    {
        $content = $this->minkContext->getSession()->getPage()->getContent();

        $data = json_decode($content, true);

        $this->jwt = $data['token'];
    }

    /**
     * @When the :headerName header contains last response token
     */
    public function theHeaderContainsLastResponseToken($headerName)
    {
        $content = $this->minkContext->getSession()->getPage()->getContent();

        $data = json_decode($content, true);

        $this->restContext->iAddHeaderEqualTo($headerName, sprintf('Bearer %s', $data['token']));
    }

    /**
     * @When the :headerName header contains a token for the last cart at restaurant with id :id
     */
    public function theHeaderContainsATokenForTheLastCartAtRestaurantWithId($headerName, $id)
    {
        $restaurant = $this->doctrine->getRepository(LocalBusiness::class)->find($id);
        $cart = $this->getLastCartFromRestaurant($restaurant);

        $jwtEncoder = $this->getContainer()->get('lexik_jwt_authentication.encoder');

        $payload = [
            'sub' => $this->iriConverter->getIriFromItem($cart, \ApiPlatform\Core\Api\UrlGeneratorInterface::ABS_URL),
        ];

        $this->restContext->iAddHeaderEqualTo($headerName, sprintf('Bearer %s', $jwtEncoder->encode($payload)));
    }

    /**
     * @Given the restaurant with id :id is closed between :start and :end
     */
    public function theRestaurantWithIdIsClosedBetweenAnd($id, $start, $end)
    {
        $restaurant = $this->doctrine->getRepository(LocalBusiness::class)->find($id);

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime($start));
        $closingRule->setEndDate(new \DateTime($end));

        $restaurant->addClosingRule($closingRule);

        $em = $this->doctrine->getManagerForClass(LocalBusiness::class);
        $em->flush();
    }

    /**
     * @Then the Tile38 collection :collectionName should contain key :keyName with point :value
     */
    public function assertTile38CollectionContainsKeyWithPoint($collectionName, $keyName, $value)
    {
        $response =
            $this->tile38->rawCommand('GET', $collectionName, $keyName);

        [ $latitude, $longitude, $timestamp ] = explode(',', $value);

        // {"type":"Point","coordinates":[2.352222,48.856613,1527855030]}

        $data = json_decode($response, true);

        Assert::assertArrayHasKey('type', $data);
        Assert::assertArrayHasKey('coordinates', $data);
        Assert::assertEquals([ $longitude, $latitude, $timestamp ], $data['coordinates']);
    }

    /**
     * @Then the payment amount of order with IRI :iri should be :value
     */
    public function assertPaymentAmountOfOrderWithIriEquals($iri, $value)
    {
        $order = $this->iriConverter->getItemFromIri($iri);

        $payment = $order->getLastPayment();

        Assert::assertEquals($value, $payment->getAmount());
    }

    /**
     * @Given the user :username has a remote push token with value :value for platform :platform
     */
    public function userHasRemotePushTokenWithValueForPlatform($username, $value, $platform)
    {
        $userManager = $this->getContainer()->get('fos_user.user_manager');
        $user = $userManager->findUserByUsername($username);

        $token = new RemotePushToken();
        $token->setToken($value);
        $token->setPlatform($platform);

        $user->addRemotePushToken($token);

        $userManager->updateUser($user);
    }

    /**
     * @Given the restaurant with id :id has deposit-refund enabled
     */
    public function enableDepositRefund($id)
    {
        $restaurant = $this->doctrine->getRepository(LocalBusiness::class)->find($id);
        $restaurant->setDepositRefundEnabled(true);

        $em = $this->doctrine->getManagerForClass(LocalBusiness::class);
        $em->flush();
    }

    /**
     * @Given the product with code :code has reusable packaging enabled with unit :units
     */
    public function enableReusablePackagingForProductWithQuantity($code, $unit)
    {
        $product = $this->doctrine->getRepository(Product::class)->findOneByCode($code);
        $product->setReusablePackagingEnabled(true);
        $product->setReusablePackagingUnit($unit);

        $em = $this->doctrine->getManagerForClass(Product::class);
        $em->flush();
    }

    /**
     * @Then all the tasks should belong to organization with name :orgName
     */
    public function allTheTasksShouldBelongToOrganizationWithName($orgName)
    {
        $org = $this->doctrine->getRepository(Organization::class)->findOneByName($orgName);

        if (!$org) {
            throw new \RuntimeException(sprintf('Organization with name "%s" not found', $orgName));
        }

        $tasks = $this->doctrine->getRepository(Task::class)->findAll();

        foreach ($tasks as $task) {
            $organization = $task->getOrganization();
            if (!$organization || $organization !== $org) {
                Assert::fail(sprintf('Task #%d does not belong to organization with name "%s"', $task->getId(), $orgName));
            }
        }
    }

    /**
     * @Given the store with name :storeName has imported tasks:
     */
    public function theStoreWithNameHasImportedTasks($storeName, TableNode $table)
    {
        $store = $this->doctrine->getRepository(Store::class)->findOneByName($storeName);

        $group = new Task\Group();
        $group->setName(sprintf('Import %s', date('d/m H:i')));

        foreach ($table->getColumnsHash() as $data) {

            $latitude = $this->faker->latitude(48.85, 48.86);
            $longitude = $this->faker->longitude(2.33, 2.34);

            $address = new Address();
            $address->setStreetAddress($data['address.streetAddress']);
            $address->setGeo(new GeoCoordinates($latitude, $longitude));

            $task = new Task();

            $task->setType($data['type']);
            $task->setAfter(new \DateTime($data['after']));
            $task->setBefore(new \DateTime($data['before']));
            $task->setAddress($address);
            $task->setOrganization($store->getOrganization());

            $group->addTask($task);
        }

        $this->doctrine->getManagerForClass(Task\Group::class)->persist($group);
        $this->doctrine->getManagerForClass(Task\Group::class)->flush();
    }

    /**
     * @Given a task with ref :ref exists and is attached to store with name :storeName
     */
    public function aTaskWithRefExistsAndIsAttachedToStoreWithName($ref, $storeName)
    {
        $store = $this->doctrine->getRepository(Store::class)->findOneByName($storeName);

        $latitude = $this->faker->latitude(48.85, 48.86);
        $longitude = $this->faker->longitude(2.33, 2.34);

        $address = new Address();
        $address->setStreetAddress('1, Rue de Rivoli, Paris, France');
        $address->setGeo(new GeoCoordinates($latitude, $longitude));

        $task = new Task();

        $task->setType('dropoff');
        $task->setAfter(new \DateTime('+1 hour'));
        $task->setBefore(new \DateTime('+2 hours'));
        $task->setAddress($address);
        $task->setOrganization($store->getOrganization());
        $task->setRef($ref);

        $this->doctrine->getManagerForClass(Task::class)->persist($task);
        $this->doctrine->getManagerForClass(Task::class)->flush();
    }
}
