<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\MenuInput;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\ProductTaxon;
use AppBundle\Entity\Sylius\Taxon;
use AppBundle\Entity\Sylius\TaxonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class RestaurantMenuSectionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly RestaurantMenuSectionProvider $sectionProvider,
        private readonly FactoryInterface $taxonFactory,
        private readonly TaxonRepository $taxonRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityPreloader $preloader)
    {}

    /**
     * @param MenuInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        // FIXME Replace by ItemProvider
        $menu = $this->taxonRepository->find($uriVariables['id']);

        if ($operation instanceof Put) {

            /** @var Taxon */
            $section = $this->sectionProvider->provide($operation, $uriVariables, $context);

            // Optimized version in raw SQL

            // 1. Remove the product from existing sections
            $qb = $this->entityManager->getRepository(ProductTaxon::class)->createQueryBuilder('tp');
            $qb
                ->delete()
                ->andWhere('tp.taxon != :section')
                ->andWhere('tp.product IN (:products)')
                ->setParameter('section', $section)
                ->setParameter('products', array_map(fn ($p) => $p->getId(), $data->products));

            $qb->getQuery()->execute();

            // 2. Clear the section
            // We clear the section to make sure positions are right
            $qb = $this->entityManager->getRepository(ProductTaxon::class)->createQueryBuilder('tp');
            $qb
                ->delete()
                ->andWhere('tp.taxon = :section')
                ->setParameter('section', $section);

            $qb->getQuery()->execute();

            foreach ($data->products as $position => $product) {
                $section->addProduct($product, $position);
            }

            if (!empty($data->name)) {
                $section->setName($data->name);
            }

            if (!empty($data->description)) {
                $section->setDescription($data->description);
            }

        } else {

            $section = $this->taxonFactory->createNew();

            $uuid = Uuid::uuid1()->toString();

            $section->setCode($uuid);
            $section->setSlug($uuid);
            $section->setName($data->name);
            $section->setDescription($data->description);

            $menu->addChild($section);
        }

        $this->entityManager->flush();

        // Dispatch event to clear Twig cache
        $restaurant = $this->taxonRepository->getRestaurantForMenu($menu);
        $this->eventDispatcher->dispatch(new GenericEvent($restaurant), 'catalog.updated');

        // Preload entities
        $this->preloader->preload([$menu], 'children');
        $children = $menu->getChildren()->toArray();
        $taxonProducts = $this->preloader->preload($children, 'taxonProducts');
        $products = $this->preloader->preload($taxonProducts, 'product');
        $this->preloader->preload($products, 'images');

        return $menu;
    }
}
