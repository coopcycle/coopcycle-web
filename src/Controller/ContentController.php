<?php

namespace AppBundle\Controller;

use League\Flysystem\Filesystem;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToReadFile;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/{_locale}', requirements: ['_locale' => '%locale_regex%'])]
class ContentController extends AbstractController
{
    #[Route(path: ['an' => '/sobre-nosotros', 'ca' => '/sobre-nosaltres', 'da' => '/om-os', 'de' => '/uber-uns', 'en' => '/about-us', 'es' => '/sobre-nosotros', 'eu' => '/guri-buruz', 'fr' => '/a-propos', 'hu' => '/rolunk', 'it' => '/riguardo-a-noi', 'pl' => '/o-nas', 'pt_BR' => '/sobre-nos', 'pt_PT' => '/sobre-nos'], name: 'about_us')]
    public function aboutUsAction(Request $request, Filesystem $assetsFilesystem, CacheInterface $appCache)
    {
        try {
            $fileExists = $assetsFilesystem->fileExists('about_us.md');
        } catch (UnableToCheckFileExistence $e) {
            throw $this->createNotFoundException();
        }

        if (!$fileExists) {
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

    private function localizeRemoteFile(Request $request, $type)
    {
        $locale = $request->getLocale();
        $files = [
            'fr' => sprintf('https://coopcycle-assets.sfo2.digitaloceanspaces.com/%s/fr.md', $type),
            'en' => sprintf('https://coopcycle-assets.sfo2.digitaloceanspaces.com/%s/en.md', $type),
            'es' => sprintf('https://coopcycle-assets.sfo2.digitaloceanspaces.com/%s/es.md', $type),
        ];

        $key = array_key_exists($locale, $files) ? $locale : 'en';

        $context = stream_context_create(['http' => ['timeout' => 5]]);

        return file_get_contents($files[$key], false, $context);
    }

    #[Route(path: '/legal', name: 'legal')]
    public function legalAction(Request $request, Filesystem $assetsFilesystem)
    {
        try {
            $customExists = $assetsFilesystem->fileExists('custom_legal.md');
        } catch (UnableToCheckFileExistence $e) {
            $customExists = false;
        }

        if ($customExists) {
            try {
                $text = $assetsFilesystem->read('custom_legal.md');
            } catch (UnableToReadFile $e) {
                $text = $this->localizeRemoteFile($request, 'legal');
            }
        } else {
            $text = $this->localizeRemoteFile($request, 'legal');
        }

        return $this->render('content/markdown.html.twig', [
            'text' => $text
        ]);
    }

    #[Route(path: '/terms', name: 'terms')]
    public function termsAction(Request $request, Filesystem $assetsFilesystem)
    {
        try {
            $customExists = $assetsFilesystem->fileExists('custom_terms.md');
        } catch (UnableToCheckFileExistence $e) {
            $customExists = false;
        }

        if ($customExists) {
            try {
                $text = $assetsFilesystem->read('custom_terms.md');
            } catch (UnableToReadFile $e) {
                $text = $this->localizeRemoteFile($request, 'terms');
            }
        } else {
            $text = $this->localizeRemoteFile($request, 'terms');
        }

        return $this->render('content/markdown.html.twig', [
            'text' => $text
        ]);
    }

    #[Route(path: '/privacy', name: 'privacy')]
    public function privacyAction(Request $request, Filesystem $assetsFilesystem)
    {
        try {
            $customExists = $assetsFilesystem->fileExists('custom_privacy.md');
        } catch (UnableToCheckFileExistence $e) {
            $customExists = false;
        }

        if ($customExists) {
            try {
                $text = $assetsFilesystem->read('custom_privacy.md');
            } catch (UnableToReadFile $e) {
                $text = $this->localizeRemoteFile($request, 'privacy');
            }
        } else {
            $text = $this->localizeRemoteFile($request, 'privacy');
        }

        return $this->render('content/markdown.html.twig', [
            'text' => $text
        ]);
    }

    #[Route(path: '/privacy-mobile', name: 'privacy-mobile')]
    public function privacyMobileAction(Request $request, Filesystem $assetsFilesystem)
    {

        $text = $this->localizeRemoteFile($request, 'privacy-mobile');

        return $this->render('content/markdown.html.twig', [
            'text' => $text
        ]);
    }

    #[Route(path: '/terms-text', name: 'terms-text')]
    public function termsTextAction(Request $request, Filesystem $assetsFilesystem)
    {
        try {
            $customExists = $assetsFilesystem->fileExists('custom_terms.md');
        } catch (UnableToCheckFileExistence $e) {
            $customExists = false;
        }

        if ($customExists) {
            try {
                $text = $assetsFilesystem->read('custom_terms.md');
            } catch (UnableToReadFile $e) {
                $text = $this->localizeRemoteFile($request, 'terms');
            }
        } else {
            $text = $this->localizeRemoteFile($request, 'terms');
        }

        return new JsonResponse([
            'text' => $text
        ]);
    }

    #[Route(path: '/privacy-text', name: 'privacy-text')]
    public function privacyTextAction(Request $request, Filesystem $assetsFilesystem)
    {
        try {
            $customExists = $assetsFilesystem->fileExists('custom_privacy.md');
        } catch (UnableToCheckFileExistence $e) {
            $customExists = false;
        }

        if ($customExists) {
            try {
                $text = $assetsFilesystem->read('custom_privacy.md');
            } catch (UnableToReadFile $e) {
                $text = $this->localizeRemoteFile($request, 'privacy');
            }
        } else {
            $text = $this->localizeRemoteFile($request, 'privacy');
        }

        return new JsonResponse([
            'text' => $text
        ]);
    }

}
