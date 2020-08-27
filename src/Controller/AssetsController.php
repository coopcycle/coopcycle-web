<?php

namespace AppBundle\Controller;

use League\Flysystem\Filesystem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AssetsController extends AbstractController
{
    /**
     * @Route("/assets/banner.svg", name="assets_banner_svg")
     */
    public function bannerAction(Request $request, Filesystem $assetsFilesystem, CacheInterface $appCache)
    {
        if (!$assetsFilesystem->has('banner.svg')) {
            throw $this->createNotFoundException();
        }

        $svg = $appCache->get('banner_svg', function (ItemInterface $item) use ($assetsFilesystem) {

            $item->expiresAfter(3600);

            return $assetsFilesystem->read('banner.svg');
        });

        $response = new Response((string) $svg);

        $response->headers->add(['Content-Type' => 'image/svg+xml']);

        return $response;
    }
}
