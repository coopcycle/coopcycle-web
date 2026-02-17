<?php

namespace AppBundle\Entity\UI;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Put;
use AppBundle\Api\Dto\HomepageBlocks;
use AppBundle\Api\State\HomepageBlockProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'Homepage',
    operations: [
        new GetCollection(uriTemplate: '/ui/homepage/blocks'),
        new Get(uriTemplate: '/ui/homepage/blocks/{id}'),
        new Put(
            uriTemplate: '/ui/homepage/blocks',
            processor: HomepageBlockProcessor::class,
            input: HomepageBlocks::class,
            security: 'is_granted("ROLE_ADMIN")'
        ),
    ],
    normalizationContext: ['groups' => ['ui.homepage']],
    denormalizationContext: ['groups' => ['ui.homepage']],
)]
class HomepageBlock
{
    private int $id;
    private int $position;

    #[Groups(['ui.homepage'])]
    public string $type;

    #[Groups(['ui.homepage'])]
    public array $data = [];

    public function getId()
    {
        return $this->id;
    }

    public function setPosition($position)
    {
        $this->position = $position;
    }

    public function getPosition()
    {
        return $this->position;
    }
}
