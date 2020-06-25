<?php

namespace AppBundle\Controller;

use AppBundle\Entity\LocalBusiness;
use Liip\ImagineBundle\Service\FilterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The "og:image" property of a restaurant used to return a 404
 * This has been fixed in https://github.com/coopcycle/coopcycle-web/commit/1d9e30d25056051ee041cefa122633684938606c
 * However, social network spiders are still hitting the page
 * Examples:
 * - /5e/ad/5ead5babae714.jpg
 * - /5e/91/5e91f47f8d875.PNG
 */
class BrokenImageController extends AbstractController
{
    /**
     * @Route("/{dir}/{subdir}/{filename}.{extension}", name="legacy_image",
     *   requirements={
     *     "dir"="[a-z0-9]{2}",
     *     "subdir"="[a-z0-9]{2}",
     *     "filename"="[a-z0-9]+",
     *     "extension"="(?i:jpg|png|jpeg|gif|webp|tif)"
     *   }
     * )
     */
    public function redirectAction($dir, $subdir, $filename, $extension, FilterService $imagineFilter)
    {
        $imageName = sprintf('%s.%s', $filename, $extension);

        if (!$restaurant = $this->getDoctrine()->getRepository(LocalBusiness::class)->findOneBy(['imageName' => $imageName])) {
            throw $this->createNotFoundException();
        }

        $imagePath = implode('/', [
            $dir,
            $subdir,
            sprintf('%s.%s', $filename, $extension)
        ]);

        $url = $imagineFilter->getUrlOfFilteredImage($imagePath, 'restaurant_thumbnail');

        return $this->redirect($url, Response::HTTP_MOVED_PERMANENTLY);
    }
}
