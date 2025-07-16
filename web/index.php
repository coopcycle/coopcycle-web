<?php

use AppBundle\Kernel as AppKernel;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

// https://symfony.com/doc/5.x/components/runtime.html#using-options
$_SERVER['APP_RUNTIME_OPTIONS'] = [
    'project_dir' => dirname($_SERVER['SCRIPT_FILENAME'], 2)
];

return function (array $context) {
    return new AppKernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
