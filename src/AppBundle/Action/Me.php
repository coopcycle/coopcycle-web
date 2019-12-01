<?php

namespace AppBundle\Action;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\ApiUser;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class Me
{
    use TokenStorageTrait;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @Route(path="/me", name="me",
     *   defaults={
     *     "_api_resource_class"=ApiUser::class,
     *     "_api_collection_operation_name"="me",
     *   },
     *   methods={"GET"}
     * )
     */
    public function meAction()
    {
        return $this->getUser();
    }
}
