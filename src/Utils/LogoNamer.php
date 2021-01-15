<?php

namespace AppBundle\Utils;

use Oneup\UploaderBundle\Uploader\File\FileInterface;
use Oneup\UploaderBundle\Uploader\Naming\NamerInterface;

class LogoNamer implements NamerInterface
{
    public function name(FileInterface $file)
    {
        return sprintf('logo-%s.%s', uniqid(), $file->getExtension());
    }
}
