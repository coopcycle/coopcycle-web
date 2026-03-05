<?php

namespace AppBundle\Uploader\Naming;

use Oneup\UploaderBundle\Uploader\File\FileInterface;
use Oneup\UploaderBundle\Uploader\Naming\NamerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\RequestStack;

class DocumentNamer implements NamerInterface
{
    public function __construct(private RequestStack $requestStack)
    {}

    /**
     * @param FileInterface $file
     * @return string The directory name.
     */
    public function name(FileInterface $file)
    {
        $originalFilename = $this->requestStack->getCurrentRequest()->get('name');

        return sprintf('%s/%s',
            Uuid::uuid4()->toString(),
            $originalFilename
        );
    }
}
