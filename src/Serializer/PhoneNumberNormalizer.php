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

    public function denormalize($data, $class, $format = null, array $context = []): ?PhoneNumber
    {
        // Also support "false"
        // https://github.com/coopcycle/coopcycle-plugins/issues/21
        if (false === $data) {
            return null;
        }

        return parent::denormalize($data, $class, $format, $context);
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        // Also support "false"
        // https://github.com/coopcycle/coopcycle-plugins/issues/21
        return 'libphonenumber\PhoneNumber' === $type && (\is_string($data) || false === $data);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            'libphonenumber\PhoneNumber' => true, // supports*() call result is cached
            'false' => true, // supports*() call result is cached
        ];
    }
}
