<?php

namespace Tests\AppBundle\Security;

use AppBundle\Security\UserManager;
use Doctrine\Persistence\ObjectManager;
use Nucleos\UserBundle\Doctrine\UserManager as DoctrineUserManager;
use Nucleos\UserBundle\Model\UserInterface;
use Nucleos\UserBundle\Util\CanonicalFieldsUpdater;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

class UserManagerTest extends TestCase
{
    use ProphecyTrait;

    private UserManager $userManager;
    private $decoratedMock;
    private $objectManagerMock;
    private $canonicalFieldsUpdaterMock;

    protected function setUp(): void
    {
        $this->decoratedMock = $this->getMockBuilder(DoctrineUserManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->objectManagerMock = $this->prophesize(ObjectManager::class);
        $this->canonicalFieldsUpdaterMock = $this->prophesize(CanonicalFieldsUpdater::class);

        $this->userManager = new UserManager(
            $this->decoratedMock,
            $this->objectManagerMock->reveal(),
            $this->canonicalFieldsUpdaterMock->reveal()
        );
    }

    private function testFindUsersByRolesSetUp(): ObjectProphecy
    {
        $userRepo = $this->prophesize(UserManager::class);
        $this->objectManagerMock
            ->getRepository(UserInterface::class)
            ->willReturn($userRepo->reveal());

        return $userRepo;
    }

    public function testFindUsersByRolesEmptyRoles(): void
    {
        $this->assertEmpty($this->userManager->findUsersByRoles([]));
    }
}
