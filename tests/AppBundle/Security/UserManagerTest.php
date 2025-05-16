<?php

namespace Tests\AppBundle\Security;

use AppBundle\Security\UserManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserManagerTest extends KernelTestCase
{
    use ProphecyTrait;

    private UserManager $userManager;
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->userManager = self::getContainer()->get(UserManager::class);

        $this->entityManager->beginTransaction();
    }


    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }

        parent::tearDown();
    }

    public function testFindUsersByRoles_emptyRoles(): void
    {
        $this->assertEmpty($this->userManager->findUsersByRoles([]));
    }

    public function testFindUsersByRoles_noUserWithRole(): void
    {
        $this->createUser(['ROLE_1'], 1);
        $this->createUser(['ROLE_2'], 2);

        $this->assertEmpty($this->userManager->findUsersByRoles(['ROLE_3']));
    }

    public function testFindUsersByRoles_someUsersHaveSomeRoles(): void
    {
        $user_2 = $this->createUser(['ROLE_2'], 2);
        $user_3 = $this->createUser(['ROLE_3'], 3);

        $result = $this->userManager->findUsersByRoles(['ROLE_1', 'ROLE_2']);

        $this->assertCount(1, $result);
        $this->assertSame($user_2, $result[0]);
    }

    public function testFindUsersByRoles_oneUsersHaveARoles(): void
    {
        $user_1 = $this->createUser(['ROLE_1'], 1);
        $user_2 = $this->createUser(['ROLE_2'], 2);
        $user_3 = $this->createUser(['ROLE_3'], 3);
        $user_4 = $this->createUser(['ROLE_1', 'ROLE_2'], 4);

        $result = $this->userManager->findUsersByRoles(['ROLE_3']);

        $this->assertCount(1, $result);
        $this->assertSame($user_3, $result[0]);
    }

    public function testFindUsersByRoles_someUsersHaveAllRoles(): void
    {
        $user_1 = $this->createUser(['ROLE_1'], 1);
        $user_2 = $this->createUser(['ROLE_2'], 2);
        $user_3 = $this->createUser(['ROLE_1', 'ROLE_2'], 3);
        $user_4 = $this->createUser(['ROLE_3'], 4);

        $result = $this->userManager->findUsersByRoles(['ROLE_1', 'ROLE_2']);

        $this->assertCount(3, $result);
    }

    public function testFindUsersByRoles_allUsersHaveAllRoles(): void
    {
        $user_1 = $this->createUser(['ROLE_1'], 1);
        $user_2 = $this->createUser(['ROLE_2'], 2);
        $user_3 = $this->createUser(['ROLE_1', 'ROLE_2'], 3);

        $result = $this->userManager->findUsersByRoles(['ROLE_1', 'ROLE_2']);

        $this->assertCount(3, $result);
    }

    // Auxiliary functions

    private function createUser($roles, $prefix): UserInterface
    {
        $user = $this->userManager->createUser();

        $user->setUsername(sprintf('%user', $prefix));
        $user->setPassword('somePassword');
        $user->setEmail(sprintf('%some@email', $prefix));
        $user->setRoles($roles);

        $this->userManager->updateUser($user);

        return $user;
    }
}
