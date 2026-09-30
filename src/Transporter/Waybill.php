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
        private array $transportersConfig = [],
    )
    {
    }

    /**
     * There is no proof until the dropoff is done, and the waybill is opt-in
     * per transporter, with `"waybill": true` in TRANSPORTERS_CONFIG.
     */
    public function isAvailable(Task $task): bool
    {
        if (!$task->isDropoff() || !$task->isDone()) {
            return false;
        }

        $importMessage = $task->getImportMessage();
        if (is_null($importMessage)) {
            return false;
        }

        return $this->transportersConfig[$importMessage->getTransporter()]['waybill'] ?? false;
    }

    /**
     * Signs the page URL: it is sent to transporters and shows a name, an
     * address and a signature, so the task id alone (or a hashid) is not
     * enough.
     *
     * Same short hash as BarcodeUtils::getToken(), which can't be called from
     * here: it is only initialized on kernel.request, not in the sync cron.
     */
    public function token(Task $task): string
    {
        return hash('xxh3', sprintf('%spod:%d', $this->secret, $task->getId()));
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

        [$signatures, $photos] = $this->splitImages($task->getImages()->toArray());

        return [
            'sender' => is_null($importMessage) ? null : $this->transporterName($importMessage->getTransporter()),
            'client_file_number' => $this->documentNumbers($point, DocumentType::CARRIER_REFERENCE)
                ?? $task->getImportReference(),
            'receipt_number' => $this->documentNumbers($point, DocumentType::WAYBILL),
            'shipper_references' => $this->documentNumbers($point, DocumentType::SHIPPER_REFERENCE),
            'consignee_orders' => $this->documentNumbers($point, DocumentType::CONSIGNEE_ORDER),
            'shipper' => $this->shipper($point),
            'consignee_address' => $this->consigneeAddress($task, $point),
            'handling_units' => $this->packageCount($point, ProductType::HANDLING_UNIT),
            'packages' => $this->packageCount($point, ProductType::PACKAGE),
            'returnables' => $this->packageCount($point, ProductType::CONSIGNED_EQUIPMENT),
            'gross_weight' => $this->measurement($point, QuantityType::CONSIGNMENT_WEIGHT)
                ?? ($task->getWeight() ? $task->getWeight() / 1000 : null),
            'volume' => $this->measurement($point, QuantityType::CONSIGNMENT_VOLUME),
            'nature_of_goods' => $this->natureOfGoods($point),
            'dangerous_goods' => $this->dangerousGoods($point),
            'signatures' => $signatures,
            'photos' => $photos,
        ];
    }

    /**
     * Apps that don't send the image type upload the signature first: without
     * a typed signature, the first untyped image is taken as the signature.
     *
     * @param array<TaskImage> $images
     * @return array{0: array<TaskImage>, 1: array<TaskImage>}
     */
    private function splitImages(array $images): array
    {
        // The collection has no order, the upload order is the id's
        usort($images, fn(TaskImage $a, TaskImage $b) => $a->getId() <=> $b->getId());

        $signatures = array_values(array_filter($images,
            fn(TaskImage $image) => $image->getType() === TaskImageType::SIGNATURE));

        if (empty($signatures)) {
            $untyped = array_values(array_filter($images, fn(TaskImage $image) => is_null($image->getType())));
            if (!empty($untyped)) {
                $signatures = [$untyped[0]];
            }
        }

        $photos = array_values(array_filter($images, fn(TaskImage $image) => !in_array($image, $signatures, true)));

        return [$signatures, $photos];
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

    /**
     * The import falls back to "INVALID ADDRESS" when geocoding fails: the
     * address as the transporter sent it is still better than that.
     */
    private function consigneeAddress(Task $task, ?Point $point): ?string
    {
        $streetAddress = $task->getAddress()->getStreetAddress();
        if ($streetAddress !== 'INVALID ADDRESS' || is_null($point)) {
            return $streetAddress;
        }

        $nads = $point->getNamesAndAddresses(NameAndAddressType::RECIPIENT);

        return empty($nads) ? $streetAddress : $nads[0]->getAddress();
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
