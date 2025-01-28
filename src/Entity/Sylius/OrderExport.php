<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Order\OrderInterface;

class OrderExport
{
    private OrderInterface $order;

    private ExportCommand $exportCommand;

    public function __construct(
        OrderInterface $order,
        ExportCommand $exportCommand
    )
    {
        $this->order = $order;
        $this->exportCommand = $exportCommand;
    }

    public function getOrder(): OrderInterface
    {
        return $this->order;
    }

    public function getExportCommand(): ExportCommand
    {
        return $this->exportCommand;
    }
}
