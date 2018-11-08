<?php

namespace AppBundle\Action;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Address;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class CreateAddress
{
    use TokenStorageTrait;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @Route(
     *     name="create_address",
     *     path="/me/addresses",
     *     defaults={"_api_resource_class"=Address::class, "_api_collection_operation_name"="create_address"},
     *     methods={"POST"}
     * )
     */
    public function __invoke($data)
    {
        $user = $this->getUser();

        $address = $data;

        $user->addAddress($address);

        return $data;
    }
}
