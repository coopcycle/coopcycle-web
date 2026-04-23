<?php

namespace AppBundle\Integration\Rdc\Enum;

enum ExternalReferenceType: string
{
    case PROVIDER_ID = 'PROVIDER_ID';
    case REQUESTOR_ID = 'REQUESTOR_ID';
    case REQUESTOR_LABEL_ID = 'REQUESTOR_LABEL_ID';
    case CUSTOMER_ID = 'CUSTOMER_ID';
}
