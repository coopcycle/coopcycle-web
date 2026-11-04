<?php

namespace Tests\AppBundle\Service\Referral;

use AppBundle\Entity\Referral\ReferralLevel;
use AppBundle\Service\Referral\ReferralLevelResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ReferralLevelResolverTest extends TestCase
{
    use ProphecyTrait;

    private function makeLevel(string $name, int $minReferralCount): ReferralLevel
    {
        $level = new ReferralLevel();
        $level->setName($name);
        $level->setMinReferralCount($minReferralCount);

        return $level;
    }

    private function createResolver(array $levelsDescSortedByThreshold, array $levelsAscSortedByThreshold): ReferralLevelResolver
    {
        $repository = $this->prophesize(ObjectRepository::class);
        $repository->findBy([], ['minReferralCount' => 'DESC'])->willReturn($levelsDescSortedByThreshold);
        $repository->findBy([], ['minReferralCount' => 'ASC'])->willReturn($levelsAscSortedByThreshold);

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $entityManager->getRepository(ReferralLevel::class)->willReturn($repository->reveal());

        return new ReferralLevelResolver($entityManager->reveal());
    }

    public function testResolveReturnsNullBelowEveryThreshold(): void
    {
        $bronze = $this->makeLevel('bronze', 1);
        $silver = $this->makeLevel('silver', 5);
        $gold = $this->makeLevel('gold', 15);

        $resolver = $this->createResolver([$gold, $silver, $bronze], [$bronze, $silver, $gold]);

        $this->assertNull($resolver->resolve(0));
    }

    public function testResolveReturnsHighestLevelReached(): void
    {
        $bronze = $this->makeLevel('bronze', 1);
        $silver = $this->makeLevel('silver', 5);
        $gold = $this->makeLevel('gold', 15);

        $resolver = $this->createResolver([$gold, $silver, $bronze], [$bronze, $silver, $gold]);

        $this->assertSame($bronze, $resolver->resolve(1));
        $this->assertSame($bronze, $resolver->resolve(4));
        $this->assertSame($silver, $resolver->resolve(5));
        $this->assertSame($silver, $resolver->resolve(14));
        $this->assertSame($gold, $resolver->resolve(15));
        $this->assertSame($gold, $resolver->resolve(100));
    }

    public function testResolveNextReturnsTheNextTierToReach(): void
    {
        $bronze = $this->makeLevel('bronze', 1);
        $silver = $this->makeLevel('silver', 5);
        $gold = $this->makeLevel('gold', 15);

        $resolver = $this->createResolver([$gold, $silver, $bronze], [$bronze, $silver, $gold]);

        $this->assertSame($bronze, $resolver->resolveNext(0));
        $this->assertSame($silver, $resolver->resolveNext(1));
        $this->assertSame($gold, $resolver->resolveNext(5));
    }

    public function testResolveNextReturnsNullAtTheTopLevel(): void
    {
        $bronze = $this->makeLevel('bronze', 1);
        $silver = $this->makeLevel('silver', 5);
        $gold = $this->makeLevel('gold', 15);

        $resolver = $this->createResolver([$gold, $silver, $bronze], [$bronze, $silver, $gold]);

        $this->assertNull($resolver->resolveNext(15));
        $this->assertNull($resolver->resolveNext(100));
    }
}
