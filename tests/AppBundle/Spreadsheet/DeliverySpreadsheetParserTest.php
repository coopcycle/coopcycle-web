<?php

namespace Tests\AppBundle\Spreadsheet;

use AppBundle\Entity\Address;
use AppBundle\Entity\Package;
use AppBundle\Service\Geocoder;
use AppBundle\Spreadsheet\AbstractSpreadsheetParser;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Prophecy\Argument;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;

class DeliverySpreadsheetParserTest extends TestCase
{
    protected function createParser(): AbstractSpreadsheetParser
    {
        $this->geocoder = $this->prophesize(Geocoder::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        $this->geocoder
            ->geocode(Argument::type('string'))
            ->willReturn(new Address());

        $this->packageRepository = $this->prophesize(ObjectRepository::class);

        $this->entityManager
            ->getRepository(Package::class)
            ->willReturn($this->packageRepository->reveal());

        return new DeliverySpreadsheetParser(
            $this->geocoder->reveal(),
            PhoneNumberUtil::getInstance(),
            'fr',
            $this->entityManager->reveal(),
        );
    }
}
