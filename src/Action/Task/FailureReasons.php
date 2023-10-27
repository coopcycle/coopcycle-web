<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Store;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;

class FailureReasons
{

    public function __construct(
        private EntityManagerInterface $em,
        private TranslatorInterface $translator
    )
    { }

    private function getDefaultReasons(): array
    {
        $path = realpath(__DIR__ . '/../../Resources/config/failure_reasons.yml');
        $parser = new YamlParser();
        $config = $parser->parseFile($path, Yaml::PARSE_CONSTANT);
        return array_map(function($failure_reason) {
            return [
                'code' => $failure_reason['code'],
                'description' => $this->translator->trans($failure_reason['description'])
            ];
        }, $config['failure_reasons']);
    }

    public function __invoke($data, Request $request)
    {

        $org = $data->getOrganization();

        if (is_null($org)) {
            return $this->getDefaultReasons();
        }

        $store = $this->em->getRepository(Store::class)->findOneBy([
            'organization' => $org
        ]);

        if (is_null($store->getFailureReasonSet())) {
            return $this->getDefaultReasons();
        }

        return $store->getFailureReasonSet()->getReasons();
    }
}
