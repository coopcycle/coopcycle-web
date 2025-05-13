<?php

namespace AppBundle\Api\Dto;

use ApiPlatform\Action\NotFoundAction;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            controller: NotFoundAction::class,
            read: false,
            output: false
        )
    ]
)]
class InvoiceLineItem
{
    #[ApiProperty(identifier: true)]
    public string $invoiceId;

    public readonly \DateTime $invoiceDate;

    #[Groups(["default_invoice_line_item"])]
    public readonly ?int $storeId;

    public readonly ?string $organizationLegalName;

    public readonly string $accountCode;

    public readonly string $product;

    #[Groups(["default_invoice_line_item"])]
    public readonly \DateTime $date;

    #[Groups(["default_invoice_line_item"])]
    public readonly int $orderId;

    #[Groups(["default_invoice_line_item"])]
    public readonly string $orderNumber;

    #[Groups(["default_invoice_line_item"])]
    public readonly string $description;

    #[Groups(["default_invoice_line_item"])]
    public readonly int $subTotal;

    #[Groups(["default_invoice_line_item"])]
    public readonly int $tax;

    #[Groups(["default_invoice_line_item"])]
    public readonly int $total;

    #[Groups(["default_invoice_line_item"])]
    public readonly array $exports;

    public function __construct(
        string $invoiceId,
        \DateTime $invoiceDate,
        ?int $storeId,
        ?string $organizationLegalName,
        string $accountCode,
        string $product,
        int $orderId,
        string $orderNumber,
        \DateTime $date,
        string $description,
        int $subTotal,
        int $tax,
        int $total,
        array $exports,
    )
    {
        $this->invoiceId = $invoiceId;
        $this->invoiceDate = $invoiceDate;
        $this->storeId = $storeId;
        $this->organizationLegalName = $organizationLegalName;
        $this->accountCode = $accountCode;
        $this->product = $product;
        $this->orderId = $orderId;
        $this->orderNumber = $orderNumber;
        $this->date = $date;
        $this->description = $description;
        $this->subTotal = $subTotal;
        $this->tax = $tax;
        $this->total = $total;
        $this->exports = $exports;
    }

    // The only reason to have separate methods
    // is because it's not possible to specify different property names
    // for different groups yet.
    // https://github.com/symfony/symfony/issues/30483


    #[Groups(["export_invoice_line_item"])]
    #[SerializedName("Organization")]
    public function getFileExportOrganization(): ?string
    {
        return $this->organizationLegalName;
    }

    #[Groups(["export_invoice_line_item"])]
    #[SerializedName("Description")]
    public function getFileExportDescription(): string
    {
        return $this->description;
    }

    #[Groups(["export_invoice_line_item"])]
    #[SerializedName("Total products (excl. VAT)")]
    public function getFileExportSubtotal(): float
    {
        return $this->subTotal / 100;
    }

    #[Groups(["export_invoice_line_item"])]
    #[SerializedName("Taxes")]
    public function getFileExportTax(): float
    {
        return $this->tax / 100;
    }

    #[Groups(["export_invoice_line_item"])]
    #[SerializedName("Total products (incl. VAT)")]
    public function getFileExportTotal(): float
    {
        return $this->total / 100;
    }


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
        return $this->organizationLegalName;
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
