<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Restaurant;
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
    protected $reader;

    public function __construct(ObjectManager $em, TokenStorageInterface $tokenStorage, Reader $reader)
    {
        $this->em = $em;
        $this->tokenStorage = $tokenStorage;
        $this->reader = $reader;
    }

    public function onKernelRequest()
    {
        $isAdmin = false;
        $isRestaurant = false;
        $restaurants = [];

        if ($user = $this->getUser()) {
            $isAdmin = $user->hasRole('ROLE_ADMIN');
            $isRestaurant = $user->hasRole('ROLE_RESTAURANT');

            if ($isRestaurant) {
                $restaurants = [];
                foreach ($user->getRestaurants() as $restaurant) {
                    $restaurants[] = $restaurant->getId();
                }
            }
        }

        if (!$isAdmin) {
            $filter = $this->em->getFilters()->enable('enabled_filter');
            $filter->setAnnotationReader($this->reader);
            $filter->setParameter('enabled', true, Type::BOOLEAN);

            if ($isRestaurant && count($restaurants) > 0) {
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
