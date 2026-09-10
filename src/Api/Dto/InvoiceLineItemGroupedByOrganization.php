<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class InvoiceLineItemGroupedByOrganization
{
    #[Groups(["default_invoice_line_item"])]
    public readonly int $storeId;

    #[Groups(["default_invoice_line_item"])]
    public readonly string $organizationLegalName;

    #[Groups(["default_invoice_line_item"])]
    public readonly string $storeName;

    #[Groups(["default_invoice_line_item"])]
    public readonly int $ordersCount;

    #[Groups(["default_invoice_line_item"])]
    public readonly int $subTotal;

    #[Groups(["default_invoice_line_item"])]
    public readonly int $tax;

    #[Groups(["default_invoice_line_item"])]
    public readonly int $total;

    #[Groups(["default_invoice_line_item"])]
    public readonly ?string $sepaMandateStatus;

    public function __construct(
        int $storeId,
        string $organizationLegalName,
        string $storeName,
        int $ordersCount,
        int $subTotal,
        int $tax,
        int $total,
        ?string $sepaMandateStatus = null
    )
    {
        $this->storeId = $storeId;
        $this->organizationLegalName = $organizationLegalName;
        $this->storeName = $storeName;
        $this->ordersCount = $ordersCount;
        $this->subTotal = $subTotal;
        $this->tax = $tax;
        $this->total = $total;
        $this->sepaMandateStatus = $sepaMandateStatus;
    }
}
