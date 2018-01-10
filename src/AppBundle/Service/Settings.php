<?php

namespace AppBundle\Service;

use Craue\ConfigBundle\Util\Config;
use Doctrine\ORM\EntityManager;

class Settings
{
    private $config;
    private $entityManager;
    private $entityName;
    private $defaults;

    public function __construct(Config $config, EntityManager $entityManager, $entityName, $defaults = [])
    {
        $this->config = $config;
        $this->entityManager = $entityManager;
        $this->entityName = $entityName;
        $this->defaults = $defaults;
    }

    public function get($name)
    {
        try {
            $this->config->get($name);
        } catch (\RuntimeException $e) {
            return $this->defaults[$name];
        }
    }

    public function set($name, $value, $section)
    {
        try {
            $this->config->set($name, $value);
        } catch (\RuntimeException $e) {
            $className = $this->entityName;
            $setting = new $className();

            $setting->setName($name);
            $setting->setValue($value);
            $setting->setSection($section);

            $this->entityManager->persist($setting);
            $this->entityManager->flush();
        }
    }
}
