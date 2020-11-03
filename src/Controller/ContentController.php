<?php

namespace AppBundle\Controller;

use League\Flysystem\Filesystem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 */
class ContentController extends AbstractController
{
    /**
     * @Route({
     *   "an": "/sobre-nosotros",
     *   "ca": "/sobre-nosaltres",
     *   "de": "/uber-uns",
     *   "en": "/about-us",
     *   "es": "/sobre-nosotros",
     *   "fr": "/a-propos",
     *   "it": "/riguardo-a-noi",
     *   "pl": "/o-nas",
     *   "pt-BR": "/sobre-nos"
     * }, name="about_us")
     */
    public function aboutUsAction(Request $request, Filesystem $assetsFilesystem, CacheInterface $appCache)
    {
        if (!$assetsFilesystem->has('about_us.md')) {
            throw $this->createNotFoundException();
        }

        $aboutUs = $appCache->get('content.about_us', function (ItemInterface $item) use ($assetsFilesystem) {

            $item->expiresAfter(300);

            return $assetsFilesystem->read('about_us.md');
        });

        return $this->render('content/markdown.html.twig', [
            'text' => $aboutUs,
        ]);
    }

    /**
     * @Route("/legal", name="legal")
     */
    public function legalAction(Filesystem $assetsFilesystem)
    {
        if ($assetsFilesystem->has('custom_legal.md')) {
            $text = $assetsFilesystem->read('custom_legal.md');
        } else {
            $text = file_get_contents('http://coopcycle.org/legal/fr.md');
        }

        return $this->render('content/markdown.html.twig', [
            'text' => $text
        ]);
    }

    /**
     * @Route("/terms", name="terms")
     */
    public function termsAction(Filesystem $assetsFilesystem)
    {
        if ($assetsFilesystem->has('custom_terms.md')) {
            $text = $assetsFilesystem->read('custom_terms.md');
        } else {
            $text = file_get_contents('http://coopcycle.org/terms/fr.md');
        }

        return $this->render('content/markdown.html.twig', [
            'text' => $text
        ]);
    }

    /**
     * @Route("/privacy", name="privacy")
     */
    public function privacyAction(Request $request, Filesystem $assetsFilesystem)
    {
        if ($assetsFilesystem->has('custom_privacy.md')) {
            $text = $assetsFilesystem->read('custom_privacy.md');
        } else {

            $locale = $request->getLocale();
            $files = [
                'fr' => 'http://coopcycle.org/privacy/fr.md',
                'en' => 'http://coopcycle.org/privacy/en.md',
            ];

            $key = array_key_exists($locale, $files) ? $locale : 'en';
            $text = file_get_contents($files[$key]);
        }

        return $this->render('content/markdown.html.twig', [
            'text' => $text
        ]);
    }
}
