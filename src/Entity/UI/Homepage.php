<?php

namespace AppBundle\Entity\UI;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Put;
use AppBundle\Api\State\HomepageProcessor;
use AppBundle\Api\State\HomepageProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'Homepage',
    operations: [
        new Get(uriTemplate: '/ui/homepage'),
        new Put(
            uriTemplate: '/ui/homepage',
            processor: HomepageProcessor::class,
            provider: HomepageProvider::class,
            // input: ShopCollectionInput::class,
            security: 'is_granted("ROLE_ADMIN")'
        ),
    ],
    normalizationContext: ['groups' => ['ui.homepage']],
    denormalizationContext: ['groups' => ['ui.homepage']],
)]
class Homepage
{
    use Timestampable;

    private $id;

    /**
     * @var ArrayCollection<Block>
     */
    #[Groups(['ui.homepage'])]
    private $blocks;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
    }

    public function setBlocks($blocks)
    {
        $this->blocks = array_map(function ($b) {
            $b->homepage = $this;
            return $b;
        }, $blocks);
    }

    public function getBlocks()
    {
        return $this->blocks;
    }
}
