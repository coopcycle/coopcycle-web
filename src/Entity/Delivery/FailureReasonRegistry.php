<?php

namespace AppBundle\Entity\Delivery;

use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FailureReasonRegistry
{
    public function __construct(
        private TranslatorInterface $translator,
        private CacheInterface $appCache)
    {}

    /**
     * @return array
     */
    public function getFailureReasons(string $group = 'default')
    {
        $config = $this->appCache->get('failure_reasons_default', function (ItemInterface $item) {

            $item->expiresAfter(60 * 60 * 24);

            $parser = new YamlParser();

            $path = realpath(__DIR__ . '/../../Resources/config/failure_reasons.yml');

            return $parser->parseFile($path, Yaml::PARSE_CONSTANT);
        });

        return array_map(function($item) {

            $item['description'] = $this->translator->trans($item['description']);

            return $item;

        }, $config['failure_reasons'][$group]);
    }
}
