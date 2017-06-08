<?php

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Order;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Address;
use AppBundle\Entity\DeliveryAddress;
use AppBundle\Entity\Delivery;
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
use Symfony\Component\HttpKernel\KernelInterface;

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

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct(ManagerRegistry $doctrine, HttpCallResultPool $httpCallResultPool)
    {
        $this->tokens = [];
        $this->doctrine = $doctrine;
        $this->manager = $doctrine->getManager();
        $this->schemaTool = new SchemaTool($this->manager);
        $this->classes = $this->manager->getMetadataFactory()->getAllMetadata();
        $this->httpCallResultPool = $httpCallResultPool;
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
    }

    /**
     * @AfterScenario
     */
    public function clearData()
    {
        $purger = new ORMPurger($this->getContainer()->get('doctrine')->getManager());
        $purger->purge();
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
     * @Given the restaurants are loaded:
     */
    public function theRestaurantsAreLoaded(TableNode $table)
    {
        $em = $this->doctrine->getManagerForClass('AppBundle:Restaurant');

        $metadata = $em->getClassMetaData(Restaurant::class);
        $metadata->setIdGenerator(new \Doctrine\ORM\Id\AssignedGenerator());
        $metadata->setIdGeneratorType(\Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_NONE);

        foreach ($table as $row) {
            $restaurant = new Restaurant();
            $restaurant->setName($row['name']);

            if (isset($row['id']) && !empty($row['id'])) {
                $property = new \ReflectionProperty(Restaurant::class, 'id');
                $property->setAccessible(true);
                $property->setValue($restaurant, $row['id']);
            }

            $address = new Address();

            $address->setStreetAddress($row['streetAddress']);

            if (isset($row['latlng']) && !empty($row['latlng'])) {
                list($lat, $lng) = explode(',', $row['latlng']);
                $address->setGeo(new GeoCoordinates($lat, $lng));
            }

            $restaurant->setAddress($address);

            $em->persist($restaurant);
        }

        $em->flush();
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

    /**
     * @Given the user is loaded:
     */
    public function theUserIsLoaded(TableNode $table)
    {
        $data = $table->getRowsHash();

        $manipulator = $this->getContainer()->get('fos_user.util.user_manipulator');
        $manipulator->create($data['username'], $data['password'], $data['email'], true, false);
    }

    /**
     * @Given the user :username is loaded:
     */
    public function theUserWithUsernameIsLoaded($username, TableNode $table)
    {
        $data = $table->getRowsHash();

        $manipulator = $this->getContainer()->get('fos_user.util.user_manipulator');
        $manipulator->create($username, $data['password'], $data['email'], true, false);
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
     * @Given the user :username has ordered at restaurant :restaurantName
     */
    public function theUserHasOrderedAtRestaurant($username, $restaurantName)
    {
        $userManager = $this->getContainer()->get('fos_user.user_manager');
        $restaurantRepository = $this->doctrine->getRepository('AppBundle:Restaurant');
        $orderManager = $this->doctrine->getManagerForClass('AppBundle:Order');

        $user = $userManager->findUserByUsername($username);
        $restaurant = $restaurantRepository->findOneByName($restaurantName);

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->setCustomer($user);
        $order->setStatus(Order::STATUS_WAITING);

        $delivery = new Delivery($order);
        $delivery->setDate(new \DateTime('+30 minutes'));
        $delivery->setOriginAddress($restaurant->getAddress());
        $delivery->setDeliveryAddress($user->getAddresses()->first());

        foreach ($restaurant->getProducts() as $product) {
            $order->addProduct($product, 1);
        }

        $orderManager->persist($order);
        $orderManager->flush();
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
     * @Given the last order from user :username has status :status
     */
    public function theLastOrderFromUserHasStatus($username, $status)
    {
        $userManager = $this->getContainer()->get('fos_user.user_manager');

        $user = $userManager->findUserByUsername($username);

        $order = $this->doctrine->getRepository('AppBundle:Order')
            ->findOneBy(['customer' => $user], ['createdAt' => 'DESC']);

        $order->setStatus($status);

        $this->doctrine->getManagerForClass('AppBundle:Order')->flush();
    }

    /**
     * @Given the last order from user :customer is accepted by courier :courier
     */
    public function theLastOrderFromUserIsAcceptedByCourier($customerUsername, $courierUsername)
    {
        $userManager = $this->getContainer()->get('fos_user.user_manager');

        $customer = $userManager->findUserByUsername($customerUsername);
        $courier = $userManager->findUserByUsername($courierUsername);

        $order = $this->doctrine->getRepository('AppBundle:Order')
            ->findOneBy(['customer' => $customer], ['createdAt' => 'DESC']);

        $order->setStatus(Order::STATUS_ACCEPTED);
        $order->setCourier($courier);

        $this->doctrine->getManagerForClass('AppBundle:Order')->flush();
    }
}
