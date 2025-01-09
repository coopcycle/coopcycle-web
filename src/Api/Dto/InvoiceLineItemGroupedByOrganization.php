<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class InvoiceLineItemGroupedByOrganization
{
    #[Groups(["default_invoice_line_item"])]
    public readonly int $organizationId;

    #[Groups(["default_invoice_line_item"])]
    public readonly string $organizationLegalName;

    #[Groups(["default_invoice_line_item"])]
    public readonly int $ordersCount;

    #[Groups(["default_invoice_line_item"])]
    public readonly int $subTotal;

    #[Groups(["default_invoice_line_item"])]
    public readonly int $tax;

    #[Groups(["default_invoice_line_item"])]
    public readonly int $total;

    public function __construct(
        int $organizationId,
        string $organizationLegalName,
        int $ordersCount,
        int $subTotal,
        int $tax,
        int $total
    )
    {
        $this->organizationId = $organizationId;
        $this->organizationLegalName = $organizationLegalName;
        $this->ordersCount = $ordersCount;
        $this->subTotal = $subTotal;
        $this->tax = $tax;
        $this->total = $total;
    }
}
