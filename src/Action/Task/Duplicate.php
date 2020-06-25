<?php

namespace AppBundle\Action\Task;

class Duplicate extends Base
{
    public function __invoke($data)
    {
        return $data->duplicate();
    }
}
