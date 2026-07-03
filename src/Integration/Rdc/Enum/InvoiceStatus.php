<?php

namespace AppBundle\Integration\Rdc\Enum;

enum InvoiceStatus: string
{
    case NOT_INVOICED = 'NOT_INVOICED';
    case PARTIALLY_INVOICED = 'PARTIALLY_INVOICED';
    case INVOICED = 'INVOICED';
}
