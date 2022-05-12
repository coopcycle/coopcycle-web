<?php

namespace AppBundle\Command;

use AppBundle\Entity\LocalBusiness;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Typesense\Client;

class IndexTypesenseDocuments extends Command
{

    public function __construct(
        Client $client,
        EntityManagerInterface $entityManager,
        NormalizerInterface $serializer
    )
    {
        $this->client = $client;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('typesense:documents:index')
            ->setDescription('Indexes existing data for a collection')
            ->addArgument(
                'collection',
                InputArgument::REQUIRED
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $collection = $input->getArgument('collection');

        try {
            $documents = $this->getDocumentsForShops();

            $this->io->text(json_encode($documents));
            // $contents = include_once(sprintf('typesense/schemas/%s.php', $collection));

            // $this->client->collections->create($contents);

        } catch (\Throwable $th) {
            $this->io->text(sprintf('There was an error creating the collection: %s', $th->getMessage()));
            return 0;
        }

        $this->io->text(sprintf('Schema for %s created successfully', $collection));

        return 1;
    }

    private function getDocumentsForShops() {
        $qb = $this->entityManager->getRepository(LocalBusiness::class)->createQueryBuilder('s');

        $qb->select(array(
            's.id',
            's.name',
            's.type',
            's.featured',
            's.exclusive',
            's.createdAt',
            's.depositRefundEnabled',
            's.loopeatEnabled',
        ))
        // ->andWhere('s.id > 20')
        // ->andWhere('s.id < 24')
        ->andWhere('s.enabled = :enabled')
        ->setParameter('enabled', true);
        // ->setMaxResults(10);

        $shops = $qb->getQuery()->getResult();

        return array_map(function ($shop) {
            return [
                "id" => $shop['id'],
                "name" => $shop['name'],
                "type" => LocalBusiness::getKeyForType($shop['type']),
                "cuisine" => $this->getShopCuisines($shop['id']),
                "category" => $this->getShopCategories($shop)
            ];
        }, $shops);
    }

    private function getShopCuisines($id) {
        $qb = $this->entityManager->getRepository(LocalBusiness::class)->createQueryBuilder('r')
            ->select('c.name')
            ->innerJoin('r.servesCuisine', 'c')
            ->andWhere('r.id = :shop_id')
            ->setParameter('shop_id', $id);

        $cuisines = $qb->getQuery()->getResult();

        return array_map(function ($cuisine) {
            return $cuisine['name'];
        }, $cuisines);
    }

    private function getShopCategories($shop) {
        $categories = [];

        if ($shop['featured']) {
            $categories[] = 'featured';
        }

        if ($shop['exclusive']) {
            $categories[] = 'exclusive';
        }

        if ($shop['depositRefundEnabled'] || $shop['loopeatEnabled']) {
            $categories[] = 'zerowaste';
        }

        return $categories;
    }

}
