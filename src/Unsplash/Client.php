<?php

namespace AppBundle\Unsplash;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class Client
{
    public function __construct(
    	private HttpClientInterface $unsplashClient,
    	private CacheInterface $appCache,
    	private SluggerInterface $slugger)
    {}

    /**
     * @return string[]
     */
    public function search(string $query, int $page = 1)
    {
    	$cacheKey = sprintf('unsplash-search-%s-p%d', $this->slugger->slug($query), $page);

    	return $this->appCache->get($cacheKey, function (ItemInterface $item) use ($query, $page) {

            $item->expiresAfter(3600);

            $response = $this->unsplashClient->request('GET', 'search/photos', [
            	'query' => [
            		'query' => $query,
            		'page' => $page,
            		'orientation' => 'landscape',
            		'content_filter' => 'high'
            	]
            ]);

            $data = $response->toArray();

            return array_map(fn (array $photo) => $photo['urls']['regular'], $data['results']);
        });
    }
}
