<?php

namespace AppBundle\Controller;

use League\Flysystem\Filesystem;
use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Liip\ImagineBundle\Exception\Imagine\Filter\NonExistingFilterException;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
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
    public function bannerAction(Request $request, Filesystem $assetsFilesystem, CacheInterface $projectCache)
    {
        if (!$assetsFilesystem->has('banner.svg')) {
            throw $this->createNotFoundException();
        }

        $svg = $projectCache->get('banner_svg', function (ItemInterface $item) use ($assetsFilesystem) {

            $item->expiresAfter(3600);

            return $assetsFilesystem->read('banner.svg');
        });

        $response = new Response((string) $svg);

        $response->headers->add(['Content-Type' => 'image/svg+xml']);

        return $response;
    }

    /**
     * @see https://github.com/liip/LiipImagineBundle/issues/971
     * @see https://github.com/liip/LiipImagineBundle/issues/1032
     *
     * @Route("/media/cache/{filter}/{path}", name="liip_imagine_cache",
     *   requirements={
     *     "filter"="^(?!resolve)[A-z0-9_-]*",
     *     "path"=".+"
     *   }
     * )
     */
    public function liipImagineCacheAction($filter, $path, Request $request,
        CacheManager $cacheManager,
        DataManager $dataManager,
        FilterManager $filterManager)
    {
        try {

            $binary = $dataManager->find($filter, $path);
            $binary = $filterManager->applyFilter($binary, $filter);

            $cacheManager->store($binary, $path, $filter);

            return new Response($binary->getContent(), 200, ['Content-Type' => $binary->getMimeType()]);

        } catch (NotLoadableException|NonExistingFilterException $e) {
            throw $this->createNotFoundException();
        }
    }
}
