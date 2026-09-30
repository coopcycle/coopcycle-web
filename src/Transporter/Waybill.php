<?php

declare(strict_types=1);

namespace AppBundle\Transporter;

use AppBundle\Entity\Task;
use AppBundle\Entity\TaskImage;
use AppBundle\Enum\TaskImageType;
use Transporter\DTO\Document;
use Transporter\DTO\Goods;
use Transporter\DTO\Package;
use Transporter\DTO\Point;
use Transporter\Enum\DocumentType;
use Transporter\Enum\NameAndAddressType;
use Transporter\Enum\ProductType;
use Transporter\Enum\QuantityType;
use Transporter\Enum\TransporterName;

/**
 * Builds what the public proof of delivery page shows for a dropoff, laid out
 * like the transporter's waybill.
 *
 * Most of it isn't stored on the task: it is re-parsed from the EDIFACT file
 * the task was imported from.
 */
class Waybill
{
    public function __construct(
        private EdifactMessageParser $edifactMessageParser,
        private string $secret,
    )
    {
    }

    /**
     * Signs the page URL: it is sent to transporters and shows a name, an
     * address and a signature, so the task id alone (or a hashid) is not
     * enough.
     */
    public function token(Task $task): string
    {
        return hash_hmac('sha256', sprintf('pod:%d', $task->getId()), $this->secret);
    }

    /**
     * @return array<string,mixed>
     */
    public function fromTask(Task $task): array
    {
        $importMessage = $task->getImportMessage();

        $point = null;
        if (!is_null($importMessage)) {
            $point = $this->edifactMessageParser->parsePoint($importMessage);
        }

        $images = $task->getImages()->toArray();

        return [
            'sender' => is_null($importMessage) ? null : $this->transporterName($importMessage->getTransporter()),
            'client_file_number' => $this->documentNumbers($point, DocumentType::CARRIER_REFERENCE)
                ?? $task->getImportReference(),
            'receipt_number' => $this->documentNumbers($point, DocumentType::WAYBILL),
            'shipper_references' => $this->documentNumbers($point, DocumentType::SHIPPER_REFERENCE),
            'consignee_orders' => $this->documentNumbers($point, DocumentType::CONSIGNEE_ORDER),
            'shipper' => $this->shipper($point),
            'handling_units' => $this->packageCount($point, ProductType::HANDLING_UNIT),
            'packages' => $this->packageCount($point, ProductType::PACKAGE),
            'returnables' => $this->packageCount($point, ProductType::CONSIGNED_EQUIPMENT),
            'gross_weight' => $this->measurement($point, QuantityType::CONSIGNMENT_WEIGHT)
                ?? ($task->getWeight() ? $task->getWeight() / 1000 : null),
            'volume' => $this->measurement($point, QuantityType::CONSIGNMENT_VOLUME),
            'nature_of_goods' => $this->natureOfGoods($point),
            'dangerous_goods' => $this->dangerousGoods($point),
            'signatures' => array_values(array_filter($images,
                fn(TaskImage $image) => $image->getType() === TaskImageType::SIGNATURE)),
            'photos' => array_values(array_filter($images,
                fn(TaskImage $image) => $image->getType() !== TaskImageType::SIGNATURE)),
        ];
    }

    private function transporterName(string $transporter): string
    {
        return match (TransporterName::tryFrom($transporter)) {
            TransporterName::DBSCHENKER => 'DB Schenker',
            TransporterName::HEPPNER => 'Heppner',
            default => $transporter,
        };
    }

    private function documentNumbers(?Point $point, DocumentType $type): ?string
    {
        if (is_null($point)) {
            return null;
        }

        $numbers = array_filter(array_map(
            fn(Document $document) => $document->getNumber(),
            $point->getDocuments($type)
        ));

        return empty($numbers) ? null : implode(', ', array_unique($numbers));
    }

    /**
     * The shipper is the origin shipper when there is one, e.g. when the
     * sender is a logistics platform shipping on behalf of someone else.
     *
     * @return array{label: ?string, address: ?string}|null
     */
    private function shipper(?Point $point): ?array
    {
        if (is_null($point)) {
            return null;
        }

        $nads = $point->getNamesAndAddresses(NameAndAddressType::ORIGIN_SHIPPER)
            ?: $point->getNamesAndAddresses(NameAndAddressType::SENDER);

        if (empty($nads)) {
            return null;
        }

        return [
            'label' => $nads[0]->getAddressLabel(),
            'address' => $nads[0]->getAddress(),
        ];
    }

    private function packageCount(?Point $point, ProductType $type): ?int
    {
        if (is_null($point)) {
            return null;
        }

        return array_sum(array_map(
            fn(Package $package) => $package->getQuantity(),
            array_filter($point->getPackages(), fn(Package $package) => $package->getType() === $type)
        ));
    }

    private function measurement(?Point $point, QuantityType $type): ?float
    {
        if (is_null($point)) {
            return null;
        }

        foreach ($point->getMesurements() as $mesurement) {
            if ($mesurement->getType() === $type) {
                return $mesurement->getQuantity();
            }
        }

        return null;
    }

    private function natureOfGoods(?Point $point): ?string
    {
        if (is_null($point)) {
            return null;
        }

        $descriptions = array_filter(array_map(
            fn(Goods $goods) => $goods->getDescription(),
            $point->getGoods()
        ));

        return empty($descriptions) ? null : implode(', ', array_unique($descriptions));
    }

    /**
     * @return array<array{un_number: ?string, name: ?string, class: ?string, packing_group: ?string}>|null
     *   Null when there's no EDIFACT data to tell
     */
    private function dangerousGoods(?Point $point): ?array
    {
        if (is_null($point)) {
            return null;
        }

        $dangerousGoods = [];
        foreach ($point->getGoods() as $goods) {
            $dg = $goods->getDangerousGoods();
            if (is_null($dg)) {
                continue;
            }
            $dangerousGoods[] = [
                'un_number' => $dg->getUnNumber(),
                'name' => $dg->getOfficialName() ?? $goods->getDescription(),
                'class' => $dg->getClass(),
                'packing_group' => $dg->getPackingGroup(),
            ];
        }

        return $dangerousGoods;
    }
}
