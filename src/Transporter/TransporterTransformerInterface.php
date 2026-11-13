<?php

namespace AppBundle\Transporter;

/**
 * Transformers are found by `coopcycle:deliveries:import` through this tag
 * (added in `Kernel::build()`),
 * each one names itself with `#[AsTaggedItem('name')]` (the `-t` option).
 */
interface TransporterTransformerInterface {

    const TAG = 'coopcycle.deliveries_transformer';

    /**
     * @param mixed $data
     */
    public function transform($data): string;
}
