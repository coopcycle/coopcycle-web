<?php

namespace AppBundle\Twig\Components;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Service\TimingRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class ShopCollection
{
    const EXPIRES_AFTER = 300;

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected LocalBusinessRepository $repository,
        protected TimingRegistry $timingRegistry,
        protected TranslatorInterface $translator,
        protected UrlGeneratorInterface $urlGenerator,
        protected CacheInterface $appCache)
    {}

    abstract public function getUrl(): string;

    abstract public function getTitle(): string;

    abstract protected function doGetShops(): array;

    public function getCacheKey(): string
    {
        $parts = [
            'homepage',
            'collection',
            strtolower(substr(strrchr(get_class($this), '\\'), 1)),
        ];

        $parts = array_merge($parts, $this->getCacheKeyParts());

        // TODO Add other cache parts
        // if ($user && ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_RESTAURANT'))) {
        //     $cacheKeySuffix = $user->getUsername();
        // } else {
        //     $cacheKeySuffix = 'anonymous';
        // }

        // if ($user && $user->getBusinessAccount()) {
        //     if ($businessContext->isActive()) {
        //         $cacheKeySuffix = sprintf('%s.%s', $cacheKeySuffix, '_business');
        //     }
        // }

        return implode('.', $parts);
    }

    protected function getCacheKeyParts(): array
    {
        return [];
    }

    public function getShops(): array
    {
        $itemsIds = $this->appCache->get($this->getCacheKey(), function (ItemInterface $item) {
            $item->expiresAfter(self::EXPIRES_AFTER);

            return array_map(fn(LocalBusiness $lb) => $lb->getId(), $this->doGetShops());
        });

        if (count($itemsIds) === 0) {
            return [];
        }

        $qb = $this->repository->createQueryBuilder('r')
            ->andWhere('r.id IN (:ids)')
            ->setParameter('ids', $itemsIds);

        return $qb->getQuery()->getResult();
    }
}
