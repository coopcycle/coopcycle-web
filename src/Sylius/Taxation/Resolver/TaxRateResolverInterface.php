<?php

namespace AppBundle\Sylius\Taxation\Resolver;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Taxation\Model\TaxableInterface;
use Sylius\Component\Taxation\Model\TaxRateInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface as BaseTaxRateResolverInterface;

interface TaxRateResolverInterface extends BaseTaxRateResolverInterface
{
    public function resolveAll(TaxableInterface $taxable, array $criteria = []): Collection;
}
