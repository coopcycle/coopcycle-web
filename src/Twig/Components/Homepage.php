<?php

namespace AppBundle\Twig\Components;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Annotation\HideSoftDeleted;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryForm;
use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\UI\HomepageBlock;
use AppBundle\Form\DeliveryEmbedType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
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
        private IriConverterInterface $iriConverter,
        private TranslatorInterface $translator)
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

    private function getDefaultBlocks(): array
    {
        $blocks = [];

        $typeByCount = array_flip($this->shopRepository->countByType());
        if (count($typeByCount) > 0) {
            krsort($typeByCount);
            $typeWithMostShops = array_shift($typeByCount);
            $blocks[] = [
                'type' => 'shop_collection',
                'data' => [
                    'component' => 'type',
                    'args' => [
                        'type' => LocalBusiness::getKeyForType($typeWithMostShops)
                    ]
                ]
            ];
        }

        $blocks[] = [
            'type' => 'shop_collection',
            'data' => [
                'component' => 'featured'
            ],
        ];

        $blocks[] = [
            'type' => 'shop_collection',
            'data' => [
                'component' => 'exclusive'
            ],
        ];

        $blocks[] = [
            'type' => 'shop_collection',
            'data' => [
                'component' => 'newest'
            ],
        ];

        $countByCuisine = $this->shopRepository->countByCuisine();

        foreach ($countByCuisine as $cuisineName => $count) {
            if ($count >= self::MIN_SHOPS_PER_CUISINE) {

                $blocks[] = [
                    'type' => 'shop_collection',
                    'data' => [
                        'component' => 'cuisine',
                        'args' => ['cuisine' => $cuisineName],
                    ],
                ];

                if (count($blocks) >= self::MAX_SECTIONS) {
                    break;
                }
            }
        }

        if ($this->getZeroWasteCount() > 0) {
            $blocks[] = [
                'type' => 'banner',
                'data' => [
                    'markdown' => '### ' . $this->translator->trans('homepage.zerowaste'),
                    'backgroundColor' => '#00dd61',
                    'colorScheme' => 'dark',
                ],
            ];
        }

        $deliveryForm = $this->getDeliveryForm();
        if (null !== $deliveryForm) {
            $blocks[] = [
                'type' => 'delivery_form',
                'data' => [
                    'form' => $this->iriConverter->getIriFromResource($deliveryForm),
                    'backgroundColor' => '#212121',
                    'colorScheme' => 'dark',
                ],
            ];
        }

        return $blocks;
    }

    public function getBlocks(): array
    {
        $blocks = $this->entityManager->getRepository(HomepageBlock::class)->findAll();

        // Default homepage
        if (count($blocks) === 0) {
            return $this->getDefaultBlocks();
        }

        return $blocks;
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
}
