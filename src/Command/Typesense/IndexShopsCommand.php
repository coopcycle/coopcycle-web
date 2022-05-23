<?php

namespace AppBundle\Command\Typesense;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Enum\FoodEstablishment;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Doctrine\ORM\EntityManagerInterface;
use AppBundle\Typesense\ShopsClient as TypesenseShopsClient;

class IndexShopsCommand extends AbstractIndexCommand
{

    public function __construct(
        TypesenseShopsClient $typesenseShopsClient,
        EntityManagerInterface $entityManager,
        NormalizerInterface $serializer
    )
    {
        $this->typesenseClient = $typesenseShopsClient;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;

        parent::__construct();
    }

    public function configure()
    {
        $this
            ->setName('typesense:shops:index')
            ->setDescription('Indexes existing shops data');
    }

    protected function getDocumentsToIndex()
    {
        $shops = $this->entityManager->getRepository(LocalBusiness::class)->findAll();

        return array_map(function ($shop) {
            return [
                "id" => strval($shop->getId()), // index with same ID so we can query by ID in database after a selection
                "name" => $shop->getName(),
                "type" => LocalBusiness::getKeyForType($shop->getType()),
                "cuisine" => $this->getShopCuisines($shop),
                "category" => $this->getShopCategories($shop),
                "enabled" => $shop->isEnabled(),
            ];
        }, $shops);
    }

    private function getShopCuisines($shop)
    {
        $isFoodEstablishment = FoodEstablishment::isValid($shop->getType());

        if (!$isFoodEstablishment) {
            return [];
        }

        $cuisines = [];
        foreach($shop->getServesCuisine() as $c) {
            $cuisines[] = $c->getName();
        }

        return $cuisines;
    }

    private function getShopCategories($shop)
    {
        $categories = [];

        if ($shop->isFeatured()) {
            $categories[] = 'featured';
        }

        if ($shop->isExclusive()) {
            $categories[] = 'exclusive';
        }

        if ($shop->isDepositRefundEnabled() || $shop->isLoopeatEnabled()) {
            $categories[] = 'zerowaste';
        }

        return $categories;
    }

}
