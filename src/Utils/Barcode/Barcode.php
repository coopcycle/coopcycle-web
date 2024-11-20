<?php

namespace AppBundle\Utils\Barcode;

final class Barcode {

    const TYPE_TASK = 1;
    const TYPE_DELIVERY = 2;

    public function __construct(
        private readonly string $raw_barcode,
        private readonly ?int   $entity_type = null,
        private readonly ?int   $entity_id = null,
        private readonly ?int   $package_task_id = null,
        private readonly ?int   $package_task_index = null
    )
    { }

    public function isContainsPackages(): bool
    {
        return !is_null($this->package_task_id) && !is_null($this->package_task_index);
    }

    public function isInternal(): bool
    {
        return !is_null($this->entity_type) && !is_null($this->entity_id);
    }

    public function getEntityId(): ?int
    {
        return $this->entity_id;
    }

    public function getEntityType(): ?int
    {
        return $this->entity_type;
    }

    public function getPackageTaskId(): ?int
    {
        return $this->package_task_id;
    }

    public function getPackageTaskIndex(): ?int
    {
        return $this->package_task_index;
    }

    public function getRawBarcode(): string
    {
        return $this->raw_barcode;
    }

    public function __toString(): string
    {
        return $this->getRawBarcode();
    }

}
