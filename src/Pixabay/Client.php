<?php

namespace AppBundle\Pixabay;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class Client
{
    public function __construct(
        private HttpClientInterface $pixabayClient,
        private CacheInterface $appCache,
        private SluggerInterface $slugger)
    {}

    /**
     * @return string[]
     */
    public function search(string $query, int $page = 1)
    {
        $cacheKey = sprintf('pixabay-search-%s-p%d', $this->slugger->slug($query), $page);

        // $this->appCache->delete($cacheKey);

        return $this->appCache->get($cacheKey, function (ItemInterface $item) use ($query, $page) {

            // To keep the Pixabay API fast for everyone, requests must be cached for 24 hours.
            // https://pixabay.com/api/docs/#api_rate_limit
            $item->expiresAfter(3600);

            $response = $this->pixabayClient->request('GET', '', [
                'query' => [
                    'q' => $query,
                    'page' => $page,
                    'orientation' => 'horizontal',
                    'image_type' => 'photo',
                    'safesearch' => 'true',
                    'category' => 'food'
                ]
            ]);

            $data = $response->toArray();

            return $data['hits'];
        });
    }
}
