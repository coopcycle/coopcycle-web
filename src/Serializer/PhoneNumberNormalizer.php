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

    public function denormalize($data, $class, $format = null, array $context = [])
    {
        // Also support "false"
        // https://github.com/coopcycle/coopcycle-plugins/issues/21
        if (false === $data) {
            return;
        }

        return parent::denormalize($data, $class, $format, $context);
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        // Also support "false"
        // https://github.com/coopcycle/coopcycle-plugins/issues/21
        return 'libphonenumber\PhoneNumber' === $type && (\is_string($data) || false === $data);
    }
}
