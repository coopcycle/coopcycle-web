<?php

namespace AppBundle\Serializer;

use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Misd\PhoneNumberBundle\Serializer\Normalizer\PhoneNumberNormalizer as BasePhoneNumberNormalizer;

class PhoneNumberNormalizer extends BasePhoneNumberNormalizer
{
    public function __construct(PhoneNumberUtil $phoneNumberUtil, $region = PhoneNumberUtil::UNKNOWN_REGION, $format = PhoneNumberFormat::E164)
    {
        parent::__construct($phoneNumberUtil, strtoupper($region), $format);
    }
}
