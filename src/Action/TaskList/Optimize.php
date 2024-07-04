<?php

namespace AppBundle\Action\TaskList;

use AppBundle\Entity\TaskList;
use AppBundle\Service\RouteOptimizer;

final class Optimize
{
    private $optimizer;

    public function __construct(RouteOptimizer $optimizer)
    {
        $this->optimizer = $optimizer;
    }

    public function __invoke($data)
    {
        $optimizedItems = $this->optimizer->optimize($data);

        $data->clear();

        foreach ($optimizedItems as $item) {
            $data->addItem($item);
        }

        return $data;
    }
}
