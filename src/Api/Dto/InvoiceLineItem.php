<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

class InvoiceLineItem
{
    public readonly string $invoiceId;

    public readonly \DateTime $invoiceDate;

    #[Groups(["default_invoice_line_item"])]
    public readonly ?int $storeId;

    #[Groups(["export_invoice_line_item"])]
    public readonly ?string $storeLegalName;

    public readonly string $accountCode;

    #[Groups(["export_invoice_line_item"])]
    public readonly string $product;

    #[Groups(["default_invoice_line_item", "export_invoice_line_item"])]
    public readonly \DateTime $date;

    #[Groups(["default_invoice_line_item"])]
    public readonly int $orderId;

    #[Groups(["default_invoice_line_item"])]
    public readonly string $orderNumber;

    #[Groups(["default_invoice_line_item", "export_invoice_line_item"])]
    public readonly string $description;

    #[Groups(["default_invoice_line_item", "export_invoice_line_item"])]
    public readonly float $subTotal;

    #[Groups(["default_invoice_line_item", "export_invoice_line_item"])]
    public readonly float $tax;

    #[Groups(["default_invoice_line_item", "export_invoice_line_item"])]
    public readonly float $total;

    public function __construct(
        string $invoiceId,
        \DateTime $invoiceDate,
        ?int $storeId,
        ?string $storeLegalName,
        string $accountCode,
        string $product,
        int $orderId,
        string $orderNumber,
        \DateTime $date,
        string $description,
        float $subTotal,
        float $tax,
        float $total
    )
    {
        $this->invoiceId = $invoiceId;
        $this->invoiceDate = $invoiceDate;
        $this->storeId = $storeId;
        $this->storeLegalName = $storeLegalName;
        $this->accountCode = $accountCode;
        $this->product = $product;
        $this->orderId = $orderId;
        $this->orderNumber = $orderNumber;
        $this->date = $date;
        $this->description = $description;
        $this->subTotal = $subTotal;
        $this->tax = $tax;
        $this->total = $total;
    }

    // The only reason to have separate methods for Odoo
    // is because it's not possible to specify different property names
    // for different groups yet.
    // https://github.com/symfony/symfony/issues/30483

    #[Groups(["odoo_export_invoice_line_item"])]
    #[SerializedName("External ID")]
    public function getOdooExternalId(): string
    {
        return $this->invoiceId;
    }

    #[Groups(["odoo_export_invoice_line_item"])]
    #[SerializedName("Invoice Date")]
    public function getOdooInvoiceDate(): string
    {
        return $this->invoiceDate->format('Y-m-d');
    }

    #[Groups(["odoo_export_invoice_line_item"])]
    #[SerializedName("Partner")]
    public function getOdooPartner(): string
    {
        return $this->storeLegalName;
    }

    #[Groups(["odoo_export_invoice_line_item"])]
    #[SerializedName("Invoice lines / Account")]
    public function getOdooAccount(): string
    {
        return $this->accountCode;
    }

    #[Groups(["odoo_export_invoice_line_item"])]
    #[SerializedName("Invoice lines / Product")]
    public function getOdooProduct(): string
    {
        return $this->product;
    }

    #[Groups(["odoo_export_invoice_line_item"])]
    #[SerializedName("Invoice lines / Label")]
    public function getOdooLabel(): string
    {
        return $this->description;
    }

    #[Groups(["odoo_export_invoice_line_item"])]
    #[SerializedName("Invoice lines / Unit Price")]
    public function getOdooUnitPrice(): float
    {
        return $this->subTotal / 100;
    }

    #[Groups(["odoo_export_invoice_line_item"])]
    #[SerializedName("Invoice lines / Quantity")]
    public function getOdooQuantity(): int
    {
        return 1;
    }

}
