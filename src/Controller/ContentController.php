<?php

namespace AppBundle\Controller;

use League\Flysystem\Filesystem;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

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
     *   "eu": "/guri-buruz",
     *   "fr": "/a-propos",
     *   "it": "/riguardo-a-noi",
     *   "pl": "/o-nas",
     *   "pt_BR": "/sobre-nos",
     *   "pt_PT": "/sobre-nos",
     *   "da": "/om-os"
     * }, name="about_us")
     */
    public function aboutUsAction(Request $request, Filesystem $assetsFilesystem, CacheInterface $projectCache)
    {
        if (!$assetsFilesystem->has('about_us.md')) {
            throw $this->createNotFoundException();
        }

        $aboutUs = $projectCache->get('content.about_us', function (ItemInterface $item) use ($assetsFilesystem) {

            $item->expiresAfter(300);

            return $assetsFilesystem->read('about_us.md');
        });

        return $this->render('content/markdown.html.twig', [
            'text' => $aboutUs,
        ]);
    }

    private function localizeRemoteFile(Request $request, $type)
    {
        $locale = $request->getLocale();
        $files = [
            'fr' => sprintf('http://coopcycle.org/%s/fr.md', $type),
            'en' => sprintf('http://coopcycle.org/%s/en.md', $type),
            'es' => sprintf('http://coopcycle.org/%s/es.md', $type),
        ];

        $key = array_key_exists($locale, $files) ? $locale : 'en';

        return file_get_contents($files[$key]);
    }

    /**
     * @Route("/legal", name="legal")
     */
    public function legalAction(Request $request, Filesystem $assetsFilesystem)
    {
        if ($assetsFilesystem->has('custom_legal.md')) {
            $text = $assetsFilesystem->read('custom_legal.md');
        } else {
            $text = $this->localizeRemoteFile($request, 'legal');
        }

        return $this->render('content/markdown.html.twig', [
            'text' => $text
        ]);
    }

    /**
     * @Route("/terms", name="terms")
     */
    public function termsAction(Request $request, Filesystem $assetsFilesystem)
    {
        if ($assetsFilesystem->has('custom_terms.md')) {
            $text = $assetsFilesystem->read('custom_terms.md');
        } else {
            $text = $this->localizeRemoteFile($request, 'terms');
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
            $text = $this->localizeRemoteFile($request, 'privacy');
        }

        return $this->render('content/markdown.html.twig', [
            'text' => $text
        ]);
    }

    /**
     * @Route("/covid-19", name="covid_19")
     */
    public function covid19Action(TranslatorInterface $translator)
    {
        return $this->render('content/raw.html.twig', [
            'text' => $translator->trans('covid_19.body', [], 'emails')
        ]);
    }

    /**
     * @Route("/terms-text", name="terms-text")
     */
    public function termsTextAction(Request $request, Filesystem $assetsFilesystem)
    {
        if ($assetsFilesystem->has('custom_terms.md')) {
            $text = $assetsFilesystem->read('custom_terms.md');
        } else {
            $text = $this->localizeRemoteFile($request, 'terms');
        }

        return new JsonResponse([
            'text' => $text
        ]);
    }

    /**
     * @Route("/privacy-text", name="privacy-text")
     */
    public function privacyTextAction(Request $request, Filesystem $assetsFilesystem)
    {
        if ($assetsFilesystem->has('custom_privacy.md')) {
            $text = $assetsFilesystem->read('custom_privacy.md');
        } else {
            $text = $this->localizeRemoteFile($request, 'privacy');
        }

        return new JsonResponse([
            'text' => $text
        ]);
    }

}
