<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\RestaurantRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Annotations\Reader;
use Doctrine\DBAL\Types\Type;

class EnabledFilterConfigurator
{
    protected $em;
    protected $tokenStorage;
    protected $restaurantRepository;
    protected $reader;
    protected $cache;

    public function __construct(
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
        RestaurantRepository $restaurantRepository,
        Reader $reader,
        CacheInterface $enabledFilterConfiguratorCache)
    {
        $this->em = $em;
        $this->tokenStorage = $tokenStorage;
        $this->restaurantRepository = $restaurantRepository;
        $this->reader = $reader;
        $this->cache = $enabledFilterConfiguratorCache;
    }

    public function onKernelRequest()
    {
        $restaurants = [];

        if ($user = $this->getUser()) {

            // If this is an admin, we don't enable the filter
            if ($user->hasRole('ROLE_ADMIN')) {
                return;
            }

            $restaurants = $this->cache->get($user->getUsername(), function (ItemInterface $item) use ($user) {

                $item->expiresAfter(600);

                $restaurants = [];
                if ($user->hasRole('ROLE_RESTAURANT')) {
                    $restaurants = $user->getRestaurants()->toArray();
                } else {
                    $restaurants = $this->restaurantRepository->findByCustomer($user);
                }

                return array_map(function (Restaurant $restaurant) {
                    return $restaurant->getId();
                }, $restaurants);
            });
        }

        $filter = $this->em->getFilters()->enable('enabled_filter');
        $filter->setAnnotationReader($this->reader);
        $filter->setParameter('enabled', true, Type::BOOLEAN);

        if (count($restaurants) > 0) {
            $filter->setParameter('restaurants', $restaurants, Type::SIMPLE_ARRAY);
        }
    }

    private function getUser()
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            return;
        }

        if (!is_object($user = $token->getUser())) {
            return;
        }

        return $user;
    }
}
