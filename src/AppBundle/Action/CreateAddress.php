<?php

namespace AppBundle\Action;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Order;
use AppBundle\Entity\Address;
use Doctrine\Common\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class CreateAddress
{
    use ActionTrait;

    /**
     * @Route(
     *     name="create_address",
     *     path="/me/addresses",
     *     defaults={"_api_resource_class"=Address::class, "_api_collection_operation_name"="create_address"}
     * )
     * @Method("POST")
     */
    public function __invoke($data)
    {
        $user = $this->getUser();

        $address = $data;

        $user->addAddress($address);

        return $data;
    }
}
