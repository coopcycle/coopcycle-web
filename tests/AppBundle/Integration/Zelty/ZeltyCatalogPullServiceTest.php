<?php

namespace Tests\AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Integration\Zelty\ZeltyCatalogPullService;
use AppBundle\Integration\Zelty\ZeltyClient;
use AppBundle\Message\Zelty\ProcessZeltyCatalog;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ZeltyCatalogPullServiceTest extends TestCase
{
    private ZeltyClient $zeltyClient;
    private MessageBusInterface $messageBus;
    private Filesystem $filesystem;
    private ZeltyCatalogPullService $pullService;

    protected function setUp(): void
    {
        $this->zeltyClient = $this->createMock(ZeltyClient::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->filesystem = $this->createMock(Filesystem::class);

        $this->pullService = new ZeltyCatalogPullService(
            $this->zeltyClient,
            $this->messageBus,
            $this->filesystem
        );
    }

    private function restaurant(int $id = 7, string $apiKey = 'secret-key'): LocalBusiness
    {
        $restaurant = $this->createMock(LocalBusiness::class);
        $restaurant->method('getId')->willReturn($id);
        $restaurant->method('getZeltyApiKey')->willReturn($apiKey);

        return $restaurant;
    }

    public function testListCatalogsAuthenticatesWithTheRestaurant(): void
    {
        $restaurant = $this->restaurant();

        $this->zeltyClient->expects($this->once())->method('setRestaurant')->with($restaurant);
        $this->zeltyClient->method('getCatalogs')->willReturn([
            ['id' => 'a1642a0f', 'name' => 'Deliveroo'],
        ]);

        $this->assertSame(
            [['id' => 'a1642a0f', 'name' => 'Deliveroo']],
            $this->pullService->listCatalogs($restaurant)
        );
    }

    public function testPullStoresTheCatalogUsingTheWebhookEnvelope(): void
    {
        $catalog = [
            'id'    => 'a1642a0f',
            'name'  => 'Deliveroo',
            'items' => [['id' => '1001', 'type' => 'dish']],
        ];

        $this->zeltyClient->method('getCatalog')->with('a1642a0f')->willReturn($catalog);

        $written = null;
        $this->filesystem->expects($this->once())
            ->method('write')
            ->willReturnCallback(function ($key, $contents) use (&$written) {
                $written = [$key, $contents];
            });

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (ProcessZeltyCatalog $message) use (&$written) {
                return $message->restaurantId === 7 && $message->s3Key === $written[0];
            }))
            ->willReturnCallback(fn($message) => new Envelope($message));

        $this->pullService->pull($this->restaurant(), 'a1642a0f');

        [$key, $contents] = $written;

        $this->assertStringStartsWith('catalog_7_', $key);
        $this->assertStringEndsWith('.json', $key);
        // The import job reads the same envelope as the catalog.push webhook.
        $this->assertSame(['data' => $catalog], json_decode($contents, true));
    }
}
