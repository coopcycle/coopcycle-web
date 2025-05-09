<?php

namespace AppBundle\Api\Dto;

use ApiPlatform\Metadata\ApiProperty;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;

final class PaymentMethodsOutput
{
    #[ApiProperty]
    #[Groups(['order'])]
    private $methods;

    public function __construct()
    {
        $this->methods = new ArrayCollection();
    }

    public function addMethod($type)
    {
        $this->methods->add(['type' => $type]);
    }

    public function getMethods()
    {
        return $this->methods;
    }
}
