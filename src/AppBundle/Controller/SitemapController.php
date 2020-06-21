<?php

namespace AppBundle\Controller;

use AppBundle\Entity\LocalBusiness;
use Cocur\Slugify\SlugifyInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{
    /**
     * @Route("/sitemap.xml", name="sitemap")
     */
    public function indexAction(SlugifyInterface $slugify)
    {
        $restaurants = $this->getDoctrine()
            ->getRepository(LocalBusiness::class)
            ->findBy(['enabled' => true]);

        // TODO Use locale_regex parameter
        $locales = ['en', 'es', 'de', 'fr', 'pl'];
        $locale = $this->getParameter('locale');
        $otherLocales = array_diff($locales, [$locale]);

        $urls = [];

        // About us
        $aboutUs = [
            'loc' => $this->generateUrl('about_us', [
                '_locale' => $locale
            ], UrlGeneratorInterface::ABSOLUTE_URL)
        ];
        foreach ($otherLocales as $otherLocale) {
            $aboutUs['alternate_urls'][$otherLocale] = $this->generateUrl('about_us', [
                '_locale' => $otherLocale
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        }
        $urls[] = $aboutUs;

        foreach ($restaurants as $restaurant) {
            $url = [
                'loc' => $this->generateUrl('restaurant', [
                    'id' => $restaurant->getId(),
                    'slug' => $slugify->slugify($restaurant->getName()),
                    '_locale' => $locale
                ], UrlGeneratorInterface::ABSOLUTE_URL)
            ];

            foreach ($otherLocales as $otherLocale) {
                $alternateUrl = $this->generateUrl('restaurant', [
                    'id' => $restaurant->getId(),
                    'slug' => $slugify->slugify($restaurant->getName()),
                    '_locale' => $otherLocale
                ], UrlGeneratorInterface::ABSOLUTE_URL);

                $url['alternate_urls'][$otherLocale] = $alternateUrl;
            }

            $urls[] = $url;
        }

        $response = new Response($this->renderView('index/sitemap.xml.twig', [
            'urls' => $urls,
        ]));
        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }
}
