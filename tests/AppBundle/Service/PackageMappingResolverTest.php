<?php

declare(strict_types=1);

namespace Tests\AppBundle\Service;

use AppBundle\Entity\Package;
use AppBundle\Entity\PackageSet;
use AppBundle\Entity\Store;
use AppBundle\Service\PackageMappingResolver;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Exception\ItemNotFoundException;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class PackageMappingResolverTest extends TestCase
{
    use ProphecyTrait;

    private TestHandler $logs;
    private Logger $logger;
    private PackageSet $packageSet;
    private Package $carton;

    protected function setUp(): void
    {
        $this->logs = new TestHandler();
        $this->logger = new Logger('test', [$this->logs]);

        $this->packageSet = $this->withId(new PackageSet(), 1);
        $this->carton = $this->withId(new Package(), 10);
        $this->packageSet->addPackage($this->carton);
    }

    public function testItResolvesEachTypeToItsPackage(): void
    {
        $resolver = $this->resolver(['/api/packages/10' => $this->carton]);

        $packages = $resolver->resolve(['PARCEL' => '/api/packages/10'], $this->logger, [], $this->packageSet);

        $this->assertSame(['PARCEL' => $this->carton], $packages);
        $this->assertFalse($this->logs->hasWarningRecords());
    }

    public function testItSkipsWhatIsNotAPackage(): void
    {
        $resolver = $this->resolver(['/api/stores/1' => new Store()]);

        $packages = $resolver->resolve([
            'PARCEL' => '/api/packages/404',
            'PALLET' => '/api/stores/1',
            'BAG' => 12,
        ], $this->logger, ['transporter' => 'TEST']);

        $this->assertSame([], $packages);
        $this->assertTrue($this->logs->hasWarningThatContains('"/api/packages/404" for type "PARCEL" is not a package'));
        $this->assertTrue($this->logs->hasWarningThatContains('"/api/stores/1" for type "PALLET" is not a package'));
        $this->assertTrue($this->logs->hasWarningThatContains('"int" for type "BAG" is not a package'));
        $this->assertSame(['transporter' => 'TEST'], $this->logs->getRecords()[0]['context']);
    }

    public function testItWarnsAboutAPackageTheStoreDoesNotOffer(): void
    {
        $otherSet = $this->withId(new PackageSet(), 2);
        $pallet = $this->withId(new Package(), 20);
        $otherSet->addPackage($pallet);
        $resolver = $this->resolver(['/api/packages/20' => $pallet]);

        $packages = $resolver->resolve(['PALLET' => '/api/packages/20'], $this->logger, [], $this->packageSet);

        // Kept: the delivery still gets it, the back office just doesn't offer it
        $this->assertSame(['PALLET' => $pallet], $packages);
        $this->assertTrue($this->logs->hasWarningThatContains('is not in the package set of the store'));
    }

    /**
     * @param array<string,object> $resources By IRI, the others are not found
     */
    private function resolver(array $resources): PackageMappingResolver
    {
        $iriConverter = $this->createMock(IriConverterInterface::class);
        $iriConverter->method('getResourceFromIri')->willReturnCallback(
            fn (string $iri) => $resources[$iri] ?? throw new ItemNotFoundException(sprintf('Not found: %s', $iri))
        );

        return new PackageMappingResolver($iriConverter);
    }

    /**
     * @template T of object
     * @param T $entity
     * @return T
     */
    private function withId(object $entity, int $id): object
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);

        return $entity;
    }
}
