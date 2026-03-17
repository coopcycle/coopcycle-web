<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class CreditNoteInput
{
    #[Groups(["order_credit_note"])]
    #[Assert\Positive]
    public int $amount;

    #[Groups(["order_credit_note"])]
    public string $couponCode;
}
