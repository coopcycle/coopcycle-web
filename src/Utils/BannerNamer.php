<?php

namespace AppBundle\Utils;

use Oneup\UploaderBundle\Uploader\File\FileInterface;
use Oneup\UploaderBundle\Uploader\Naming\NamerInterface;

class BannerNamer implements NamerInterface
{
    public function name(FileInterface $file)
    {
        return 'banner.svg';
    }
}
