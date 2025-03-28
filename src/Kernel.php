<?php

namespace AppBundle;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * {@inheritdoc}
     */
    public function getProjectDir(): string
    {
        if (isset($_ENV['APP_PROJECT_DIR'])) {
            return $_ENV['APP_PROJECT_DIR'];
        } elseif (isset($_SERVER['APP_PROJECT_DIR'])) {
            return $_SERVER['APP_PROJECT_DIR'];
        }

        return parent::getProjectDir();
    }

    /**
     * {@inheritdoc}
     */
    public function getLogDir(): string
    {
        // Just to add the "s"
        return $_SERVER['APP_LOG_DIR'] ?? ($this->getProjectDir().'/var/logs');
    }
}
