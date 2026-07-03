<?php

namespace AppBundle\ExpressionLanguage;

use AppBundle\Entity\Address;
use AppBundle\Entity\Zone;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class ZoneExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    private $zoneRepository;
    private $cache = [];

    public function __construct(EntityRepository $zoneRepository)
    {
        $this->zoneRepository = $zoneRepository;
    }

    public function getFunctions()
    {
        $inZoneCompiler = function (Address $address, $zoneName) {
            // FIXME Need to test compilation
            return sprintf('($zone = $this->zoneRepository->findOneBy([\'name\' => %1$s]) && $zone->containsAddress($address))', $zoneName);
        };

        $inZoneEvaluator = function ($arguments, ?Address $address, $zoneName) {

            if (null === $address) {
                return false;
            }

            if ($zone = $this->getZone($zoneName)) {
                return $zone->containsAddress($address);
            }

            return false;
        };

        $outZoneCompiler = function (Address $address, $zoneName) {
            // FIXME Need to test compilation
            return sprintf('($zone = $this->zoneRepository->findOneBy([\'name\' => %1$s]) && !$zone->containsAddress($address))', $zoneName);
        };

        $outZoneEvaluator = function ($arguments, ?Address $address, $zoneName) {

            if (null === $address) {
                return false;
            }

            if ($zone = $this->zoneRepository->findOneBy(['name' => $zoneName])) {
                return !$zone->containsAddress($address);
            }

            return true;
        };

        return array(
            new ExpressionFunction('in_zone', $inZoneCompiler, $inZoneEvaluator),
            new ExpressionFunction('out_zone', $outZoneCompiler, $outZoneEvaluator),
        );
    }

    private function getZone($zoneName): ?Zone
    {
        if (isset($this->cache[$zoneName])) {
            return $this->cache[$zoneName];
        }

        $zone = $this->zoneRepository->findOneBy(['name' => $zoneName]);

        if (null !== $zone) {
            $this->cache[$zoneName] = $zone;
        }

        return $zone;
    }
}
