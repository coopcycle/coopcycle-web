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
use Typesense\Client;

class IndexShopsCommand extends AbstractIndexCommand
{
    protected $COLLECTION_NAME = 'shops';

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

    protected function deleteIndexedDocuments()
    {
        $this->client->collections[$this->COLLECTION_NAME]->documents->delete(['filter_by' => 'enabled:[true,false]']);
    }

}
