<?php

namespace AppBundle\Command;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psonic\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment as TwigEnvironment;

Class BuildIndexCommand extends Command
{
    private $entityManager;
    private $ingestClient;
    private $controlClient;
    private $sonicSecretPassword;
    private $namespace;

    public function __construct(
        EntityManagerInterface $entityManager,
        Client $ingestClient,
        Client $controlClient,
        TwigEnvironment $twig,
        string $sonicSecretPassword,
        string $namespace)
    {
        $this->entityManager = $entityManager;
        $this->ingestClient = $ingestClient;
        $this->controlClient = $controlClient;
        $this->twig = $twig;
        $this->sonicSecretPassword = $sonicSecretPassword;
        $this->namespace = $namespace;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:index:build')
            ->setDescription('Build Sonic index.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ingest  = new \Psonic\Ingest($this->ingestClient);
        $control = new \Psonic\Control($this->controlClient);

        $ingest->connect($this->sonicSecretPassword);
        $control->connect($this->sonicSecretPassword);

        $allCollectionName = 'store:*:deliveries';
        $deliveryRepo = $this->entityManager->getRepository(Delivery::class);

        $q = $deliveryRepo->createQueryBuilder('d')
            ->addOrderBy('d.id', 'DESC')
            ->setMaxResults(10000)
            ->getQuery();

        foreach ($q->toIterable() as $delivery) {

            $html = $this->twig->render('sonic/delivery.html.twig', [
                'delivery' => $delivery,
            ]);

            $response = $ingest->push($allCollectionName, $this->namespace, $delivery->getId(), $html);
            $status = $response->getStatus(); // Should be "OK"

            $this->io->text(sprintf('[%s] %s: %s', $allCollectionName, $delivery->getId(), $status));

            $store = $delivery->getStore();

            if ($store) {
                $collectionName = sprintf('store:%d:deliveries', $store->getId());

                $response = $ingest->push($collectionName, $this->namespace, $delivery->getId(), $html);
                $status = $response->getStatus(); // Should be "OK"

                $this->io->text(sprintf('[%s] %s: %s', $collectionName, $delivery->getId(), $status));
            }
        }

        $ingest->disconnect();
        $control->disconnect();

        return 0;
    }
}
