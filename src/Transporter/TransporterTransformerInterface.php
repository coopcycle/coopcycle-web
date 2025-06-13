<?php

namespace AppBundle\Transporter;

interface TransporterTransformerInterface {

    /**
     * @param mixed $data
     */
    public function transform($data): string;
}
