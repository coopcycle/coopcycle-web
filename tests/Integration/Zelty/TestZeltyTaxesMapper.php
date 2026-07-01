<?php

namespace Tests\Integration\Zelty;

use AppBundle\Integration\Zelty\ZeltyTaxesMapper;

/**
 * Test stub for ZeltyTaxesMapper.
 *
 * Uses a static map so the tax category state is shared across both kernel
 * instances (context kernel and driver kernel) that run in the same PHP process
 * during Behat tests, avoiding the need to mock HTTP clients cross-kernel.
 */
class TestZeltyTaxesMapper extends ZeltyTaxesMapper
{
    private static array $staticTaxCategoryMap = [];

    public static function setStaticTaxCategoryMap(array $map): void
    {
        self::$staticTaxCategoryMap = $map;
    }

    public function importTaxes(): array
    {
        if (self::$staticTaxCategoryMap !== []) {
            return self::$staticTaxCategoryMap;
        }

        return parent::importTaxes();
    }
}
