<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Delivery;
use AppBundle\Message\IndexDeliveries;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psonic\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Twig\Environment as TwigEnvironment;

class IndexDeliveriesHandler implements MessageHandlerInterface
{
    public function __construct(
        EntityManagerInterface $entityManager,
        Client $ingestClient,
        Client $controlClient,
        TwigEnvironment $twig,
        string $sonicSecretPassword,
        string $namespace,
        LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->ingestClient = $ingestClient;
        $this->controlClient = $controlClient;
        $this->twig = $twig;
        $this->sonicSecretPassword = $sonicSecretPassword;
        $this->namespace = $namespace;
        $this->logger = $logger;
    }

    public function __invoke(IndexDeliveries $message)
    {
        $ingest  = new \Psonic\Ingest($this->ingestClient);
        $control = new \Psonic\Control($this->controlClient);

        $ingest->connect($this->sonicSecretPassword);
        $control->connect($this->sonicSecretPassword);

        $allCollectionName = 'store:*:deliveries';

        $qb = $this->entityManager->getRepository(Delivery::class)
            ->createQueryBuilder('d');

        $q = $qb
            ->andWhere(
                $qb->expr()->in('d.id', $message->getIds())
            )
            ->getQuery();

        foreach ($q->toIterable() as $delivery) {

            $html = $this->twig->render('sonic/delivery.html.twig', [
                'delivery' => $delivery,
            ]);

            $response = $ingest->push($allCollectionName, $this->namespace, $delivery->getId(), $html);
            $status = $response->getStatus(); // Should be "OK"

            $this->logger->info(sprintf('[%s] %s: %s', $allCollectionName, $delivery->getId(), $status));

            $store = $delivery->getStore();

            if ($store) {
                $collectionName = sprintf('store:%d:deliveries', $store->getId());

                $response = $ingest->push($collectionName, $this->namespace, $delivery->getId(), $html);
                $status = $response->getStatus(); // Should be "OK"

                $this->logger->info(sprintf('[%s] %s: %s', $collectionName, $delivery->getId(), $status));
            }
        }

        $ingest->disconnect();
        $control->disconnect();
    }
}
