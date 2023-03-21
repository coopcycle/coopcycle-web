<?php

namespace AppBundle\Action\Order;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderInvitation;
use AppBundle\Service\EmailManager;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CreateInvitation
{
    private $jwtEncoder;
    private $iriConverter;
    private $emailManager;

    public function __construct(
        JWTEncoderInterface $jwtEncoder,
        IriConverterInterface $iriConverter,
        EmailManager $emailManager
    )
    {
        $this->jwtEncoder = $jwtEncoder;
        $this->iriConverter = $iriConverter;
        $this->emailManager = $emailManager;
    }

    public function __invoke(Order $data, Request $request)
    {
    	$invitation = $data->getInvitation();

    	if (null === $invitation) {
    		$data->createInvitation();
    	}

        return $data;
    }
}

