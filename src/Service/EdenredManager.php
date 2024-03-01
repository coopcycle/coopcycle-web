<?php

namespace AppBundle\Service;

use DOMDocument;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

class EdenredManager
{
    private $coopcycleAppName;

    public function __construct(
        string $coopcycleAppName
    )
    {
        $this->coopcycleAppName = $coopcycleAppName;
    }

    public function createSyncFileAndSendToEdenred(array $restaurants): bool
    {
        $date = new \DateTime();

        // TODO: should we have a DB sequence for this?
        $number = '001';
        $fileName = sprintf('COOPCYCLE_%s_RAEN_TRDQ_%s_%s_%s.xml', strtoupper($this->coopcycleAppName),
            $date->format('Ymd'), $date->format('His'), $number);

        $xml = $this->createXML($restaurants, $fileName, $date, $number);

        // $filesystem = new Filesystem(new Ftp([
        //     'host' => 'gateway2.edenred.fr',
        //     'username' => 'TP_COOPCYCLE',
        //     'password' => 'TP_COOPCYCLE_pass',
        //     'port' => 22,
        //     'root' => './OUT',
        //     'ssl' => false
        // ]));
        $adapter = new Local(__DIR__.'/../../edenred-files/IN');
        $filesystem = new Filesystem($adapter);

        return $filesystem->write($fileName, $xml);
    }

    public function createXML(array $restaurants, string $fileName, \DateTime $dateTime, string $number)
    {
        $xml = new DOMDocument('1.0', 'UTF-8');

        $documentElement = $xml->createElement('DOCUMENT');
        $documentElement->setAttributeNS(
            'http://www.w3.org/2000/xmlns/', // xmlns namespace URI
            'xmlns:xsd',
            'http://www.w3.org/2001/XMLSchema'
        );
        $documentElement->setAttributeNS(
            'http://www.w3.org/2000/xmlns/', // xmlns namespace URI
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $documentElement->setAttribute('tpFlux', 'TRDQ');
        $documentElement->setAttribute('source', 'COOPCYCLE'); // Nom de MarketPlace OU RAEN
        $documentElement->setAttribute('destination', 'RAEN');

        $documentElement->setAttribute('date', $dateTime->format('YmdHis')); // Date execution batch YYYYMMDDHHmmss

        /*
        <!-- Nom du fichier NomMarketPlace_RAEN_TRDQ_AAAAMMJJ_HHNNSS_Num�roOrdre.xml-->
        */
        $documentElement->setAttribute('nom', $fileName);

        $documentElement->setAttribute('ordre', $number); //Numero de sequence Padleft 0

        $parsedRestaurants = 0;
        foreach ($restaurants as $restaurant) {

            if (null !== $restaurant->getAdditionalPropertyValue('siret')) {
                $parsedRestaurants++;
                $PDVElement = $xml->createElement('PDV');
                $PDVElement->setAttribute('Siret', $restaurant->getAdditionalPropertyValue('siret'));
                $PDVElement->setAttribute('Adresse', $restaurant->getAddress()->getStreetAddress());
                $PDVElement->setAttribute('Ville', $restaurant->getAddress()->getAddressLocality());
                $PDVElement->setAttribute('CodePostal', $restaurant->getAddress()->getPostalCode());
                $PDVElement->setAttribute('Addinfo', $restaurant->getId());
                $documentElement->appendChild($PDVElement);
            }

            $restaurant->setEdenredSyncSent(true);
        }

        $documentElement->setAttribute('nombreAffilies', $parsedRestaurants); // Nombre des affilies du fichier
        $xml->appendChild($documentElement);
        $xml->formatOutput = TRUE;

        return $xml->saveXML();
    }
}
