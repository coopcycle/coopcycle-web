<?php

namespace AppBundle\Utils\Barcode;

use Picqer\Barcode\BarcodeGenerator;

class BarcodeUtils {


    public static function parse(string $barcode): Barcode {
        $matches = [];
        if (!preg_match(
            '/6767(?<instance>[0-9]{3})(?<entity>[1-2])(?<id>[0-9]+)(P(?<package>[0-9]+))?(U(?<unit>[0-9]+))?8076/',
            $barcode,
            $matches,
            PREG_OFFSET_CAPTURE
        )) { return new Barcode($barcode); }

        return new Barcode(
            $barcode,
            $matches['entity'][0],
            $matches['id'][0],
            $matches['package'][0] ?? null,
            $matches['unit'][0] ?? null
        );
    }

}
