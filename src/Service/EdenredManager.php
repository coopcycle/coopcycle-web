<?php

namespace AppBundle\Service;

use AppBundle\Entity\LocalBusiness;
use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;

class EdenredManager
{
    private $entityManager;
    private $logger;
    private $coopcycleAppName;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        string $coopcycleAppName
    )
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
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

    public function readEdenredFileAndSynchronise()
    {
        // $filesystem = new Filesystem(new Ftp([
        //     'host' => 'gateway2.edenred.fr',
        //     'username' => 'TP_COOPCYCLE',
        //     'password' => 'TP_COOPCYCLE_pass',
        //     'port' => 22,
        //     'root' => './OUT',
        //     'ssl' => false
        // ]));
        $adapter = new Local(__DIR__.'/../../edenred-files/OUT');
        $filesystem = new Filesystem($adapter);

        $contents = array_filter(
            $filesystem->listContents(''),
            fn ($file) => str_starts_with($file['path'], sprintf('RAEN_COOPCYCLE_%s_TRDQ_', strtoupper($this->coopcycleAppName)))
                & $file['size'] > 0 & $file['type'] === 'file'
        );

        foreach ($contents as $object) {
            $this->logger->info(sprintf('Reading content from file "%s"', $object['path']));

            $document = new DOMDocument();
            $document->loadXML($filesystem->read($object['path']));

            $merchants = $document->getElementsByTagName('PDV');

            foreach ($merchants as $merchant) {
                $this->logger->info(sprintf('Reading merchant with SIRET "%s"', $merchant->getAttribute('Siret')));

                if ($merchant->hasAttribute('Addinfo')) {
                    $restaurant = $this->entityManager->getRepository(LocalBusiness::class)
                        ->find(intval($merchant->getAttribute('Addinfo')));

                    if (!$restaurant) {
                        $this->logger->error(sprintf('Restaurant with ID %s not found', $merchant->getAttribute('Addinfo')));
                    } else {
                        $this->logger->info(sprintf('Merchant associated with restaurant #%d - "%s"',
                            $restaurant->getId(), $restaurant->getName()));
                        if ($merchant->hasAttribute('MID')) {
                            $this->logger->info(sprintf('Merchant ID "%s"', $merchant->getAttribute('MID')));

                            $restaurant->setEdenredMerchantId($merchant->getAttribute('MID'));

                            if ($merchant->hasAttribute('StatutMID') && $merchant->getAttribute('StatutMID') === "1") {
                                $this->logger->info('Merchant already enabled for using TR cards');
                                $restaurant->setEdenredTRCardEnabled(true);
                            } else {
                                $this->logger->info('Merchant not yet enabled for using TR cards');
                            }

                            $this->entityManager->flush();
                        } else {
                            $this->logger->info('Merchant ID not yet available');
                        }
                    }
                } else {
                    $this->logger->error('Error: Addinfo attribute was not provided');
                }
            }
        }
    }

    private function createXML(array $restaurants, string $fileName, \DateTime $dateTime, string $number)
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
        $documentElement->setAttribute('source', sprintf('COOPCYCLE_%s', strtoupper($this->coopcycleAppName))); // Nom de MarketPlace OU RAEN
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
