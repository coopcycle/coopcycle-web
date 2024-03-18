<?php

namespace AppBundle\Service;

use AppBundle\Entity\LocalBusiness;
use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\StorageAttributes;
use Psr\Log\LoggerInterface;

class EdenredManager
{
    private $entityManager;
    private $logger;
    private $coopcycleAppName;
    private $sftpHost;
    private $sftpPort;
    private $sftpUsername;
    private $sftpPrivateKeyFile;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        string $coopcycleAppName,
        string $sftpHost,
        string $sftpPort,
        string $sftpUsername,
        string $sftpPrivateKeyFile
    )
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->coopcycleAppName = $coopcycleAppName;
        $this->sftpHost = $sftpHost;
        $this->sftpPort = $sftpPort;
        $this->sftpUsername = $sftpUsername;
        $this->sftpPrivateKeyFile = $sftpPrivateKeyFile;
    }

    public function createSyncFileAndSendToEdenred(array $restaurants): void
    {
        $date = new \DateTime();

        // For now the number will be the same since we are not checking this field
        $number = '001';
        $fileName = sprintf('COOPCYCLE_%s_RAEN_TRDQ_%s_%s_%s.xml', strtoupper($this->coopcycleAppName),
            $date->format('Ymd'), $date->format('His'), $number);

        $xml = $this->createXML($restaurants, $fileName, $date, $number);

        $filesystem = new Filesystem(new SftpAdapter(
            new SftpConnectionProvider(
                $this->sftpHost,
                $this->sftpUsername,
                null, // password
                $this->sftpPrivateKeyFile,
                null, // passphrase
                $this->sftpPort,
                false,
                30, // timeout (optional, default: 10)
                10, // max tries (optional, default: 4)
            ),
            '/sftp/IN', // path
        ));

        $filesystem->write($fileName, $xml);
    }

    public function readEdenredFileAndSynchronise()
    {
        $filesystem = new Filesystem(new SftpAdapter(
            new SftpConnectionProvider(
                $this->sftpHost,
                $this->sftpUsername,
                null, // password
                $this->sftpPrivateKeyFile,
                null, // passphrase
                $this->sftpPort,
                false,
                30, // timeout (optional, default: 10)
                10, // max tries (optional, default: 4)
            ),
            '/sftp/OUT', // path
        ));

        $allPaths = $filesystem->listContents('')
            ->filter(fn (StorageAttributes $attributes) => $attributes->isFile())
            ->filter(fn (FileAttributes $attributes) =>
                str_starts_with($attributes->path(), sprintf('RAEN_COOPCYCLE_%s_TRDQ_', strtoupper($this->coopcycleAppName))) & $attributes->fileSize() > 0)
            ->map(fn (FileAttributes $attributes) => $attributes->path())
            ->toArray();

        $this->logger->info(sprintf('%d files at Edenred SFTP for sync', count($allPaths)));

        $contents = array_map(function($path) use($filesystem) {
            $this->logger->info(sprintf('Reading content from file "%s"', $path));
            return $filesystem->read($path);
        }, $allPaths);

        foreach ($contents as $content) {
            $document = new DOMDocument();
            $document->loadXML($content);

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
        <!-- Nom du fichier NomMarketPlace_RAEN_TRDQ_AAAAMMJJ_HHNNSS_NumeroOrdre.xml-->
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
