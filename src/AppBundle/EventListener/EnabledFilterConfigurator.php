<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\RestaurantRepository;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Annotations\Reader;
use Doctrine\DBAL\Types\Type;

class EnabledFilterConfigurator
{
    protected $em;
    protected $tokenStorage;
    protected $restaurantRepository;
    protected $reader;

    public function __construct(
        ObjectManager $em,
        TokenStorageInterface $tokenStorage,
        RestaurantRepository $restaurantRepository,
        Reader $reader)
    {
        $this->em = $em;
        $this->tokenStorage = $tokenStorage;
        $this->restaurantRepository = $restaurantRepository;
        $this->reader = $reader;
    }

    public function onKernelRequest()
    {
        $isAdmin = false;
        $isRestaurant = false;
        $restaurants = [];

        if ($user = $this->getUser()) {

            // If this is an admin, we don't enable the filter
            $isAdmin = $user->hasRole('ROLE_ADMIN');
            if ($isAdmin) {
                return;
            }

            $isRestaurant = $user->hasRole('ROLE_RESTAURANT');
            if ($isRestaurant) {
                $restaurants = [];
                foreach ($user->getRestaurants() as $restaurant) {
                    $restaurants[] = $restaurant;
                }
            } else {
                $restaurants = $this->restaurantRepository->findByCustomer($user);
            }
        }

        if (!$isAdmin) {
            $filter = $this->em->getFilters()->enable('enabled_filter');
            $filter->setAnnotationReader($this->reader);
            $filter->setParameter('enabled', true, Type::BOOLEAN);

            if (count($restaurants) > 0) {

                $restaurants = array_map(function (Restaurant $restaurant) {
                    return $restaurant->getId();
                }, $restaurants);

                $filter->setParameter('restaurants', $restaurants, Type::SIMPLE_ARRAY);
            }
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
