<?php

namespace AppBundle\Twig\Components;

use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Service\TimingRegistry;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class ShopCollection
{
    public function __construct(
        protected LocalBusinessRepository $repository,
        protected TimingRegistry $timingRegistry,
        protected TranslatorInterface $translator,
        protected UrlGeneratorInterface $urlGenerator)
    {
    }

    abstract public function getUrl(): string;

    abstract public function getTitle(): string;

    abstract public function getShops(): array;
}
