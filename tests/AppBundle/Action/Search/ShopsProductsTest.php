<?php

declare(strict_types=1);

namespace Tests\AppBundle\Action\Search;

use ACSEO\TypesenseBundle\Client\CollectionClient;
use ACSEO\TypesenseBundle\Manager\CollectionManager;
use AppBundle\Action\Search\ShopsProducts;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\OpenApi;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class ShopsProductsTest extends TestCase
{
    use ProphecyTrait;

    private function createCollectionManager()
    {
        $collectionManager = $this->prophesize(CollectionManager::class);
        $collectionManager->getManagedClassNames()->willReturn([
            'coopcycle_chorlton_products' => Product::class,
            'coopcycle_chorlton_shops' => LocalBusiness::class,
        ]);

        return $collectionManager;
    }

    public function testSearch(): void
    {
        $collectionClient = $this->prophesize(CollectionClient::class);
        $collectionClient->multisearch(Argument::type('array'), null)->willReturn([
            'results' => [
                [
                    'hits' => [
                        ['document' => ['id' => '1', 'name' => 'Nodaiwa']],
                    ],
                ],
                [
                    'hits' => [
                        ['document' => ['id' => '2', 'name' => 'Pizza Margherita']],
                    ],
                ],
            ],
        ]);

        $logger = $this->prophesize(LoggerInterface::class);
        $logger->warning(Argument::cetera())->shouldNotBeCalled();

        $action = new ShopsProducts(
            $collectionClient->reveal(),
            $this->createCollectionManager()->reveal(),
            $logger->reveal()
        );

        $this->assertEquals([
            ['id' => '1', 'name' => 'Nodaiwa', 'result_type' => 'shop'],
            ['id' => '2', 'name' => 'Pizza Margherita', 'result_type' => 'product'],
        ], $action(new Request(['q' => 'nodai'])));
    }

    public function testSearchWithFailedCollection(): void
    {
        // Each search of a multisearch fails on its own, without "hits"
        $collectionClient = $this->prophesize(CollectionClient::class);
        $collectionClient->multisearch(Argument::type('array'), null)->willReturn([
            'results' => [
                [
                    'hits' => [
                        ['document' => ['id' => '1', 'name' => 'Nodaiwa']],
                    ],
                ],
                [
                    'code' => 404,
                    'error' => 'Not found.',
                ],
            ],
        ]);

        $logger = $this->prophesize(LoggerInterface::class);
        $logger
            ->warning(
                'Typesense search on collection "coopcycle_chorlton_products" failed: Not found.',
                [
                    'collection' => 'coopcycle_chorlton_products',
                    'code' => 404,
                    'error' => 'Not found.',
                ]
            )
            ->shouldBeCalledOnce();

        $action = new ShopsProducts(
            $collectionClient->reveal(),
            $this->createCollectionManager()->reveal(),
            $logger->reveal()
        );

        $this->assertEquals([
            ['id' => '1', 'name' => 'Nodaiwa', 'result_type' => 'shop'],
        ], $action(new Request(['q' => 'nodai'])));
    }

    public function blankQueryProvider(): array
    {
        return [
            [ [] ],
            [ ['q' => ''] ],
            [ ['q' => '  '] ],
        ];
    }

    /**
     * @dataProvider blankQueryProvider
     */
    public function testBlankQueryReturnsEmptyList(array $query): void
    {
        $collectionClient = $this->prophesize(CollectionClient::class);
        $collectionClient->multisearch(Argument::cetera())->shouldNotBeCalled();

        $action = new ShopsProducts(
            $collectionClient->reveal(),
            $this->createCollectionManager()->reveal(),
            $this->prophesize(LoggerInterface::class)->reveal()
        );

        $this->assertEquals([], $action(new Request($query)));
    }

    public function testHtmlRequestReturnsSwaggerUiData(): void
    {
        $collectionClient = $this->prophesize(CollectionClient::class);
        $collectionClient->multisearch(Argument::cetera())->shouldNotBeCalled();

        $action = new ShopsProducts(
            $collectionClient->reveal(),
            $this->createCollectionManager()->reveal(),
            $this->prophesize(LoggerInterface::class)->reveal()
        );

        // This is what ApiPlatform\Symfony\Bundle\SwaggerUi\SwaggerUiProvider does
        $openApi = new OpenApi(new Info('CoopCycle', '1.0'), [], new Paths());

        $request = new Request(['q' => 'nodai']);
        $request->attributes->set('data', $openApi);

        $this->assertSame($openApi, $action($request));
    }
}
