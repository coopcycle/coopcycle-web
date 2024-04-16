<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Model\CustomFailureReasonInterface;
use AppBundle\Entity\Organization;
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

    private function loadFailureReasonsConfig(): array
    {
        $path = realpath(__DIR__ . '/../../Resources/config/failure_reasons.yml');
        $parser = new YamlParser();
        return $parser->parseFile($path, Yaml::PARSE_CONSTANT);
    }

    private function getDefaultReasons(): array
    {
        $config = $this->loadFailureReasonsConfig();
        return array_map(function($failure_reason) {
            return [
                'code' => $failure_reason['code'],
                'description' => $this->translator->trans($failure_reason['description'])
            ];
        }, $config['failure_reasons']['default']);
    }

    private function getTransporterReasons(string $transporter): array
    {
        $config = $this->loadFailureReasonsConfig();
        return array_map(function($failure_reason) {
            return [
                'code' => $failure_reason['code'],
                'description' => $this->translator->trans($failure_reason['description']),
                'option' => $failure_reason['option'] ?? null,
                'only' => $failure_reason['only'] ?? null
            ];
        }, $config['failure_reasons'][$transporter]);
    }

    private function getFailureReasons(CustomFailureReasonInterface $entity)
    {
        if (
            $entity instanceof Store &&
            $entity->isDBSchenkerEnabled()
        ) {
            return $this->getTransporterReasons('dbschenker');
        }
        $set = $entity->getFailureReasonSet();
        if (is_null($set)) {
            return $this->getDefaultReasons();
        }
        return $set->getReasons();
    }

    public function __invoke($data, Request $request)
    {

        $org = $data->getOrganization();

        if (is_null($org)) {
            return $this->getDefaultReasons();
        }

        $reverse = $this->em->getRepository(Organization::class)
            ->reverseFindByOrganizarionID($org);

        if (empty($reverse)) {
            return $this->getDefaultReasons();
        }
        $reverse = $reverse[0];

        if (!is_null($reverse['store_id'])) {
            $store = $this->em->getRepository(Store::class)
                ->find($reverse['store_id']);
            return $this->getFailureReasons($store);
        }

        if (!is_null($reverse['restaurant_id'])) {
            $restaurant = $this->em->getRepository(LocalBusiness::class)
                ->find($reverse['restaurant_id']);
            return $this->getFailureReasons($restaurant);
        }

        return $this->getDefaultReasons();

    }
}
