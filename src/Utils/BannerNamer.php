<?php

namespace AppBundle\Utils;

use Oneup\UploaderBundle\Uploader\File\FileInterface;
use Oneup\UploaderBundle\Uploader\Naming\NamerInterface;

class BannerNamer implements NamerInterface
{
    public function name(FileInterface $file)
    {
        $mimeType = $file->getMimeType();
        if ($mimeType === 'image/png') return 'banner_background.png';
        if (in_array($mimeType, ['image/jpeg', 'image/jpg'])) return 'banner_background.jpg';
        return 'banner.svg';
    }
}
