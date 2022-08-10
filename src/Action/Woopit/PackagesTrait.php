<?php

namespace AppBundle\Action\Woopit;

use AppBundle\Entity\Task;
use AppBundle\Entity\Woopit\QuoteRequest as WoopitQuoteRequest;

trait PackagesTrait
{
    protected function parseAndApplyPackages(WoopitQuoteRequest $data, Task $task)
    {
        if ($data->packages) {
            $packagesString = '';

            foreach($data->packages as $package) {
                if (!empty($packagesString)) {
                    $packagesString .= ', ';
                }
                $packagesString .= $package['quantity'];
                if (isset($package['weight'])) {
                    $packagesString .= ' x ' . $package['weight']['value'] . ' ' . $package['weight']['unit'];
                }
            }

            $task->setComments(sprintf('Packages: %s', $packagesString));
        }
    }
}
