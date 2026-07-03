<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Zone;
use AppBundle\ExpressionLanguage\ZoneExpressionLanguageProvider;
use AppBundle\Utils\RestaurantFilter;
use AppBundle\Service\RoutingInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class RestaurantFilterTest extends KernelTestCase
{
    use ProphecyTrait;

    private $expressionLanguage;
    private $filter;
    private $zoneRepository;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->expressionLanguage = new ExpressionLanguage();

        $routing = self::getContainer()->get(RoutingInterface::class);

        $geojson = json_decode(file_get_contents(realpath(__DIR__ . '/../Resources/geojson/paris_south_area.geojson')), true);

        $zone = new Zone();
        $zone->setGeoJSON($geojson);

        $this->zoneRepository = $this->prophesize(EntityRepository::class);
        $this->zoneRepository
            ->findOneBy(['name' => 'paris_south_area'])
            ->willReturn($zone);

        $this->expressionLanguage->registerProvider(
            new ZoneExpressionLanguageProvider($this->zoneRepository->reveal())
        );

        $this->filter = new RestaurantFilter(
            $routing,
            $this->expressionLanguage
        );
    }

    private function createRestaurant(array $latLng, string $expression)
    {
        $address = new Address();
        $address->setLatLng($latLng);

        $restaurant = new LocalBusiness();
        $restaurant->setAddress($address);
        $restaurant->setDeliveryPerimeterExpression($expression);

        return $restaurant;
    }

    public function testSimpleUseCase()
    {
        $resto1 = $this->createRestaurant(
            // 58 Bd du Montparnasse
            [48.846167980463385, 2.3225888219305357],
            'in_zone(dropoff.address, "paris_south_area")'
        );

        // 12 Rue du Départ
        $results = $this->filter->matchingLatLng([ $resto1 ], 48.84323711991344, 2.3232778969273857);

        $this->assertContains($resto1, $results);

        // 20 Rue de la Folie Méricourt
        $results = $this->filter->matchingLatLng([ $resto1 ], 48.86263288337096, 2.3739018969285306);

        $this->assertNotContains($resto1, $results);
    }

    public function testAllRestaurantsSharingSameZone()
    {
        $restaurants = [];
        for ($i = 0; $i < 10; $i++) {
            $restaurants[] = $this->createRestaurant(
                // 58 Bd du Montparnasse
                [48.846167980463385, 2.3225888219305357],
                'in_zone(dropoff.address, "paris_south_area")'
            );
        }

        // 12 Rue du Départ
        $results = $this->filter->matchingLatLng($restaurants, 48.84323711991344, 2.3232778969273857);

        $this->zoneRepository
            ->findOneBy(['name' => 'paris_south_area'])
            ->shouldHaveBeenCalledTimes(1);
    }
}
