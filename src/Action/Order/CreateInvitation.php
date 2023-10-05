<?php

namespace AppBundle\Action\Order;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderInvitation;
use Symfony\Component\HttpFoundation\Request;

class CreateInvitation
{
    public function __invoke(Order $data, Request $request)
    {
    	$invitation = $data->getInvitation();

    	if (null === $invitation) {
    		$data->createInvitation();
    	}

        return $data;
    }
}

