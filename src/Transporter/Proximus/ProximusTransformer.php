<?php

namespace AppBundle\Transporter\Proximus;

use AppBundle\Transporter\TransporterTransformerInterface;
use DateTime;
use League\Csv\Reader;
use League\Csv\Writer;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Yaml;

class ProximusTransformer implements TransporterTransformerInterface {

    private readonly array $addresses;

    public function __construct()
    {
        $path = realpath(__DIR__ . '/../../Resources/config/r9_proximus_addresses.yml');
        $parser = new YamlParser();
        $this->addresses = $parser->parseFile($path, Yaml::PARSE_CONSTANT);
    }

    /**
     * Create date range for pickup or dropoff
     * @return array<int,DateTime>
     */
    private function createDateRange(\DateTime $date, string $type): array
    {
        $from = clone $date;
        $to = clone $date;

        switch ($type) {
            case 'pickup':
                $from->setTime(9, 0, 0);
                $to->setTime(10, 0, 0);
                break;
            case 'dropoff':
                $from->setTime(9, 45, 0);
                $to->setTime(15, 0, 0);
                break;
            default:
                throw new \InvalidArgumentException("Invalid type: {$type}. Expected 'pickup' or 'dropoff'.");
        }

        return [$from, $to];
    }

    /**
     * Convert datetime to string format
     */
    private function datetimeToString(\DateTime $datetime): string
    {
        return $datetime->format('Y-m-d H:i');
    }

    /**
     * Convert date range to string format
     * @param array<int,DateTime> $dateRange
     */
    private function dateRangeToString(array $dateRange): string
    {
        return $this->datetimeToString($dateRange[0]) . ' - ' . $this->datetimeToString($dateRange[1]);
    }

    /**
     * Transform the CSV data
     */
    public function transform($tournee_csv): string
    {
        $routeReader = Reader::createFromString($tournee_csv);
        $routeReader->setDelimiter(';');
        $routeReader->setHeaderOffset(0);
        $routeRecords = $routeReader->getRecords();

        $output = Writer::createFromString();

        $headers = [
            'pickup.address',
            'pickup.address.name',
            'pickup.address.description',
            'pickup.address.telephone',
            'pickup.comments',
            'pickup.timeslot',
            'pickup.tags',
            'pickup.metadata',
            'dropoff.address',
            'dropoff.address.name',
            'dropoff.address.description',
            'dropoff.address.telephone',
            'dropoff.comments',
            'dropoff.timeslot',
            'dropoff.packages',
            'dropoff.tags',
            'dropoff.metadata',
            'weight'
        ];

        $output->insertOne($headers);

        foreach ($routeRecords as $routeRecord) {
            $addressCode = $routeRecord['Leveradres colli'];

            if (!isset($this->addresses[$addressCode])) {
                continue;
            }

            $addressRecord = $this->addresses[$addressCode];

            $dateStr = $routeRecord['Leverdatum'];
            $date = \DateTime::createFromFormat('d/m/Y', $dateStr);

            if (!$date) {
                // Try alternative format with leading zeros
                $date = \DateTime::createFromFormat('j/n/Y', $dateStr);
            }

            if (!$date) {
                continue;
            }

            // Create pickup and dropoff timeslots
            $pickupRange = $this->createDateRange($date, 'pickup');
            $dropoffRange = $this->createDateRange($date, 'dropoff');

            $transformedRecord = [
                'pickup.address' => 'Rue du Nord Belge 6, 4020 Liège, Belgique',
                'pickup.address.name' => 'Centre logistique Proximus',
                'pickup.address.description' => 'LSP53',
                'pickup.address.telephone' => '',
                'pickup.comments' => '',
                'pickup.timeslot' => $this->dateRangeToString($pickupRange),
                'pickup.tags' => '',
                'pickup.metadata' => '',
                'dropoff.address' => $addressRecord['street_name'] ?? '',
                'dropoff.address.name' => $addressRecord['address_name'] ?? '',
                'dropoff.address.description' => '',
                'dropoff.address.telephone' => '',
                'dropoff.comments' => '',
                'dropoff.timeslot' => $this->dateRangeToString($dropoffRange),
                'dropoff.packages' => 'Box proximus=1',
                'dropoff.tags' => '',
                'dropoff.metadata' => 'barcode=' . $routeRecord['Nummer Colli'],
                'weight' => $routeRecord['Gewicht'] ?? ''
            ];

            $output->insertOne($transformedRecord);
        }

        return $output->toString();
    }
  }
