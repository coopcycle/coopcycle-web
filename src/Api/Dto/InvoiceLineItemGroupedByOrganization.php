<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class InvoiceLineItemGroupedByOrganization
{
    // IRI of the organization: either a Store ("/api/stores/{id}")
    // or a restaurant/LocalBusiness ("/api/restaurants/{id}")
    #[Groups(["default_invoice_line_item"])]
    public readonly string $organizationId;

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

    public function __construct(
        string $organizationId,
        string $organizationLegalName,
        string $storeName,
        int $ordersCount,
        int $subTotal,
        int $tax,
        int $total
    )
    {
        $this->organizationId = $organizationId;
        $this->organizationLegalName = $organizationLegalName;
        $this->storeName = $storeName;
        $this->ordersCount = $ordersCount;
        $this->subTotal = $subTotal;
        $this->tax = $tax;
        $this->total = $total;
    }
}
