<?php

namespace AppBundle\Twig\Components;

use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Service\TimingRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class ShopCollection
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected LocalBusinessRepository $repository,
        protected TimingRegistry $timingRegistry,
        protected TranslatorInterface $translator,
        protected UrlGeneratorInterface $urlGenerator)
    {
    }

    // TODO Implement caching
    /*
    $user = $this->getUser();

    if ($user && ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_RESTAURANT'))) {
        $cacheKeySuffix = $user->getUsername();
    } else {
        $cacheKeySuffix = 'anonymous';
    }

    if ($user && $user->getBusinessAccount()) {
        if ($businessContext->isActive()) {
            $cacheKeySuffix = sprintf('%s.%s', $cacheKeySuffix, '_business');
        }
    }
    */

    abstract public function getUrl(): string;

    abstract public function getTitle(): string;

    abstract public function getShops(): array;
}
