<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class InvoiceLineItemSepaChargeResult
{
    #[Groups(["default_invoice_line_item"])]
    public readonly int $storeId;

    #[Groups(["default_invoice_line_item"])]
    public readonly string $storeName;

    /**
     * One of "charged", "skipped_no_mandate", "failed".
     */
    #[Groups(["default_invoice_line_item"])]
    public readonly string $status;

    #[Groups(["default_invoice_line_item"])]
    public readonly int $amount;

    #[Groups(["default_invoice_line_item"])]
    public readonly ?string $paymentIntentId;

    #[Groups(["default_invoice_line_item"])]
    public readonly ?string $error;

    public function __construct(
        int $storeId,
        string $storeName,
        string $status,
        int $amount,
        ?string $paymentIntentId = null,
        ?string $error = null
    )
    {
        $this->storeId = $storeId;
        $this->storeName = $storeName;
        $this->status = $status;
        $this->amount = $amount;
        $this->paymentIntentId = $paymentIntentId;
        $this->error = $error;
    }
}
