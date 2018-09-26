<?php

namespace AppBundle\EventListener;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Annotations\Reader;
use Doctrine\DBAL\Types\Type;

class EnabledFilterConfigurator
{
    protected $em;
    protected $authorizationChecker;
    protected $reader;

    public function __construct(ObjectManager $em, AuthorizationCheckerInterface $authorizationChecker, Reader $reader)
    {
        $this->em = $em;
        $this->authorizationChecker = $authorizationChecker;
        $this->reader = $reader;
    }

    public function onKernelRequest()
    {
        $isAdmin = false;

        try {
            $isAdmin = $this->authorizationChecker->isGranted('ROLE_ADMIN');
        } catch (AuthenticationCredentialsNotFoundException $e) {}

        if (!$isAdmin) {
            $filter = $this->em->getFilters()->enable('enabled_filter');
            $filter->setParameter('enabled', true, Type::BOOLEAN);
            $filter->setAnnotationReader($this->reader);
        }
    }
}
