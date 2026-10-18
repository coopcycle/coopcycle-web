<?php

namespace Tests\AppBundle\Doctrine;

use AppBundle\Entity\Task\VersionedCollectionInterface;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\Tour;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * `TaskList` and `Tour` both carry a version, but only one of them is locked
 * on it. The difference is deliberate and easy to erase by copying one mapping
 * onto the other, so it is pinned here.
 *
 * @see VersionedCollectionInterface
 */
class VersionedCollectionMappingTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testATourIsLockedOnItsVersion()
    {
        $metadata = $this->entityManager->getClassMetadata(Tour::class);

        $this->assertTrue(
            $metadata->isVersioned,
            'TourProcessor locks on this; LockMode::OPTIMISTIC throws without it'
        );
        $this->assertSame('version', $metadata->versionField);
    }

    /**
     * Arming optimistic locking here would make concurrent `set_items` writes
     * fail — the busiest write path in the product, used by every shipped
     * version of the mobile app — while buying no protection, because nothing
     * sends `If-Match` for a task list yet. That trade only becomes worth
     * making in step 2, together with the endpoints and the client handling.
     *
     * @see https://github.com/coopcycle/coopcycle-web/issues/5372
     */
    public function testATaskListIsNotLockedOnItsVersionYet()
    {
        $metadata = $this->entityManager->getClassMetadata(TaskList::class);

        $this->assertFalse(
            $metadata->isVersioned,
            'this would turn every concurrent set_items write into a 409'
        );
        $this->assertArrayHasKey(
            'version',
            $metadata->fieldMappings,
            'the version is still read and written, just not enforced'
        );
    }

    public function testBothAreDeclaredAsVersionedCollections()
    {
        foreach ([TaskList::class, Tour::class] as $class) {
            $this->assertContains(
                VersionedCollectionInterface::class,
                class_implements($class),
                sprintf('%s is skipped by TaskCollectionVersionSubscriber without it', $class)
            );
        }
    }
}
