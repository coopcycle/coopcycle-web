<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class PaymentRefundInput
{
    #[Groups(["payment_refund"])]
    public int $amount;

    #[Groups(["payment_refund"])]
    #[Assert\Choice(['merchant', 'platform'])]
    public string $liableParty;

    #[Groups(["payment_refund"])]
    public string $comments = '';
}
