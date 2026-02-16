<?php

namespace AppBundle\Twig\Components;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Annotation\HideSoftDeleted;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryForm;
use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\UI\Block;
use AppBundle\Form\DeliveryEmbedType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
#[HideSoftDeleted]
class Homepage
{
    const MAX_SECTIONS = 8;
    const MIN_SHOPS_PER_CUISINE = 3;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LocalBusinessRepository $shopRepository,
        private FormFactoryInterface $formFactory,
        private IriConverterInterface $iriConverter)
    {}

    public function getZeroWasteCount(): int
    {
        return $this->shopRepository->countZeroWaste();
    }

    public function getHubs()
    {
        return $this->entityManager->getRepository(Hub::class)->findBy([
            'enabled' => true
        ]);
    }

    public function getSections(): array
    {
        $sections = [];

        // TODO Maybe Make sure there is a minimal number?
        $typeByCount = array_flip($this->shopRepository->countByType());
        krsort($typeByCount);
        $typeWithMostShops = array_shift($typeByCount);

        $sections[] = [
            'component' => 'ShopCollection:Type',
            'props' => ['type' => LocalBusiness::getKeyForType($typeWithMostShops)]
        ];

        $sections[] = [
            'component' => 'ShopCollection:Featured'
        ];

        $sections[] = [
            'component' => 'ShopCollection:Exclusive'
        ];

        $sections[] = [
            'component' => 'ShopCollection:Newest'
        ];

        $countByCuisine = $this->shopRepository->countByCuisine();

        foreach ($countByCuisine as $cuisineName => $count) {
            if ($count >= self::MIN_SHOPS_PER_CUISINE) {

                $sections[] = [
                    'component' => 'ShopCollection:Cuisine',
                    'props' => ['cuisine' => $cuisineName],
                ];

                if (count($sections) >= self::MAX_SECTIONS) {
                    break;
                }
            }
        }

        return $sections;
    }

    public function getBlocks(): array
    {
        return $this->entityManager->getRepository(Block::class)->findAll();
    }

    public function getDeliveryForm(): ?DeliveryForm
    {
        $qb = $this->entityManager
            ->getRepository(DeliveryForm::class)
            ->createQueryBuilder('f');

        $qb->where('f.showHomepage = :showHomepage');
        $qb->setParameter('showHomepage', ($showHomepage = true));
        $qb->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function createDeliveryFormView(DeliveryForm $deliveryForm): FormView
    {
        return $this->formFactory->createNamed('delivery', DeliveryEmbedType::class, new Delivery(), [
            'with_weight'      => $deliveryForm->getWithWeight(),
            'with_vehicle'     => $deliveryForm->getWithVehicle(),
            'with_time_slot'   => $deliveryForm->getTimeSlot(),
            'with_package_set' => $deliveryForm->getPackageSet(),
        ])->createView();
    }

    public function getResourceFromIri(string $iri): ?DeliveryForm
    {
        return $this->iriConverter->getResourceFromIri($iri);
    }
}
