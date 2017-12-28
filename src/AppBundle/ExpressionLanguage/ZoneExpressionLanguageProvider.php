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

    public function __construct(EntityRepository $zoneRepository)
    {
        $this->zoneRepository = $zoneRepository;
    }

    public function getFunctions()
    {
        $zoneRepository = $this->zoneRepository;

        $compiler = function (Address $address, $zoneName) use ($zoneRepository) {
            // FIXME Need to test compilation
            return sprintf('($zone = $zoneRepository->findOneBy([\'name\' => %1$s]) && $zone->containsAddress($address))', $zoneName);
        };

        $evaluator = function ($arguments, Address $address, $zoneName) use ($zoneRepository) {

            if ($zone = $zoneRepository->findOneBy(['name' => $zoneName])) {
                return $zone->containsAddress($address);
            }

            return false;
        };

        return array(
            new ExpressionFunction('in_zone', $compiler, $evaluator)
        );
    }
}
