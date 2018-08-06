<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\ApiUser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DeliveryManagerTest extends KernelTestCase
{
    private $client;
    private $searchManager;
    private $usersIndex;

    protected function setUp()
    {
        parent::setUp();

        self::bootKernel();

        $this->client = static::$kernel->getContainer()->get('Elastica\Client');
        $this->searchManager = static::$kernel->getContainer()->get('coopcycle.search_manager');

        $this->usersIndex = $this->searchManager->getUsersIndex();
    }

    private static function createUser($id, $username, $firstName, $lastName)
    {
        $user = new ApiUser();
        $user->setUsername($username);
        $user->setGivenName($firstName);
        $user->setFamilyName($lastName);

        $class = new \ReflectionClass($user);
        $property = $class->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($user, $id);

        return $user;
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->usersIndex->delete();
    }

    public function testCreateDocumentFromUser()
    {
        $user = self::createUser(1, 'john', 'John', 'Doe');

        $document = $this->searchManager->createDocumentFromUser($user);

        $this->assertEquals('user', $document->getType());
        $this->assertEquals(1, $document->getId());

        $this->assertEquals('john', $document->get('username'));
        $this->assertEquals('John', $document->get('firstname'));
        $this->assertEquals('Doe', $document->get('lastname'));
    }

    public function testSearchUsers()
    {
        $bonnie = self::createUser(1, 'bonnie', 'Bonnie', 'Parker');
        $clyde = self::createUser(2, 'clyde', 'Clyde', 'Barrow');

        $documents = [
            $this->searchManager->createDocumentFromUser($bonnie),
            $this->searchManager->createDocumentFromUser($clyde),
        ];

        $this->usersIndex->addDocuments($documents);
        $this->usersIndex->refresh();

        $expectations = [
            'bonn' => 'bonnie',
            'bonnie' => 'bonnie',
            'parker' => 'bonnie'
        ];

        foreach ($expectations as $q => $username) {

            $matches = $this->searchManager->searchUsers($q);
            $this->assertEquals(1, count($matches), sprintf('Query "%s" returned no results', $q));

            $documents = $matches->getDocuments();
            $this->assertEquals($username, $documents[0]->get('username'));
        }
    }
}
