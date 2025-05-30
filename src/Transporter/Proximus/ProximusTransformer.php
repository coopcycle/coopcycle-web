<?php

namespace AppBundle\Transporter\Proximus;

use AppBundle\Transporter\TransporterTransformerInterface;
use League\Csv\Reader;
use League\Csv\Writer;

/**
 * Rayon9Convert - Hardcoded PHP conversion of Rayon9 Proximus logic
 *
 * Specific implementation for processing Proximus delivery routes
 */
class ProximusTransformer implements TransporterTransformerInterface {

    /**
     * Create a date range for pickup (9:00-10:00)
     *
     * @param \DateTime $date The base date
     * @return array Associative array with 'from' and 'to' DateTime objects
     */
    public function createPickupDateRange(\DateTime $date): array {
        $from = clone $date;
        $to = clone $date;

        $from->setTime(9, 0, 0);
        $to->setTime(10, 0, 0);

        return ['from' => $from, 'to' => $to];
    }

    /**
     * Create a date range for dropoff (9:45-15:00)
     *
     * @param \DateTime $date The base date
     * @return array Associative array with 'from' and 'to' DateTime objects
     */
    public function createDropoffDateRange(\DateTime $date): array {
        $from = clone $date;
        $to = clone $date;

        $from->setTime(9, 45, 0);
        $to->setTime(15, 0, 0);

        return ['from' => $from, 'to' => $to];
    }

    /**
     * Convert DateTime to formatted string
     *
     * @param \DateTime $datetime The datetime to format
     * @return string Formatted datetime string (Y-m-d H:i)
     */
    public function datetimeToString(\DateTime $datetime): string {
        return $datetime->format('Y-m-d H:i');

    }


    /**
     * Convert a date range to string
     *
     * @param array $dateRange Array with 'from' and 'to' keys containing DateTime objects
     * @return string Formatted date range string
     */
    public function dateRangeToString(array $dateRange): string {
        return $this->datetimeToString($dateRange['from']) . ' - ' . $this->datetimeToString($dateRange['to']);
    }

    /**
     * Parse CSV content to array using League\Csv
     *
     * @param string $content CSV content
     * @return array Array of data rows
     */
    public function parseCsvContent(string $content): array {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $csv = Reader::createFromStream($stream);
        $csv->setDelimiter(',');
        $csv->setEnclosure('"');
        $csv->setEscape('\\');
        $csv->setHeaderOffset(0);

        $records = iterator_to_array($csv->getRecords());

        fclose($stream);
        return $records;
    }

    /**
     * Convert processed data to CSV using League\Csv
     *
     * @param array $data Array of data rows
     * @return string CSV content
     */
    public function convertToCsv(array $data): string {
        if (empty($data)) {
            return '';
        }

        $stream = fopen('php://temp', 'r+');

        $csv = Writer::createFromStream($stream);
        $csv->setDelimiter(',');
        $csv->setEnclosure('"');
        $csv->setEscape('\\');

        // Write headers
        $csv->insertOne(array_keys(reset($data)));

        // Write data
        $csv->insertAll(array_map('array_values', $data));

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    /**
     * Handle the conversion of route and address data
     *
     * @param array $routeData Array of route data rows
     * @param array $addressData Array of address data rows
     * @return array Processed data for export
     */
    public function handle(array $routeData, array $addressData): array {
        // Create a lookup for address data by code
        $addressLookup = [];
        foreach ($addressData as $address) {
            $code = $address['Code'] ?? null;
            if ($code) {
                $addressLookup[$code] = $address;
            }
        }

        $result = [];

        foreach ($routeData as $route) {
            $addressCode = $route['Leveradres colli'] ?? null;

            // Skip if we can't find matching address or missing address code
            if (!$addressCode || !isset($addressLookup[$addressCode])) {
                continue;
            }

            $address = $addressLookup[$addressCode];

            // Get delivery date
            $deliveryDate = $route['Leverdatum'] ?? null;
            if (!$deliveryDate) {
                continue;
            }

            // Parse the delivery date (format: Y/m/d H:i:s)
            $date = \DateTime::createFromFormat('Y/m/d H:i:s', $deliveryDate);

            if (!$date) {
                continue; // Skip if date parsing fails
            }

            $pickupDateRange = $this->createPickupDateRange($date);
            $dropoffDateRange = $this->createDropoffDateRange($date);

            $parcelNumber = isset($route['Nummer Colli']) ? (int)$route['Nummer Colli'] : '';
            $weight = $route['Gewicht'] ?? '';

            $result[] = [
                'pickup.address' => 'Rue du Nord Belge 6, 4020 Liège, Belgique',
                'pickup.address.name' => 'Centre logistique Proximus',
                'pickup.address.description' => 'LSP53',
                'pickup.address.telephone' => '',
                'pickup.comments' => '',
                'pickup.timeslot' => $this->dateRangeToString($pickupDateRange),
                'pickup.tags' => '',
                'pickup.metadata' => '',
                'dropoff.address' => $address['Adress'] ?? '',
                'dropoff.address.name' => $address['Adress.name'] ?? '',
                'dropoff.address.description' => '',
                'dropoff.address.telephone' => '',
                'dropoff.comments' => '',
                'dropoff.timeslot' => $this->dateRangeToString($dropoffDateRange),
                'dropoff.tags' => '',
                'dropoff.metadata' => 'barcode=' . $parcelNumber,
                'weight' => $weight
            ];
        }

        return $result;
    }

    /**
     * Process CSV content and return processed data
     *
     * @param string $routeCsvContent Route CSV content (Tournée CSV)
     * @param string $addressCsvContent Address CSV content (Code addresses CSV)
     * @return array Processed data
     */
    public function process(string $routeCsvContent, string $addressCsvContent): array {
        $routeData = $this->parseCsvContent($routeCsvContent);
        $addressData = $this->parseCsvContent($addressCsvContent);

        return $this->handle($routeData, $addressData);
    }

    /**
     * Process CSVs and return CSV output string
     *
     * @param string $routeCsvContent Route CSV content (Tournée CSV)
     * @param string $addressCsvContent Address CSV content (Code addresses CSV)
     * @return string Processed CSV content ready for export
     */
    public function processAndGetCsv(string $routeCsvContent, string $addressCsvContent): string {
        $processedData = $this->process($routeCsvContent, $addressCsvContent);
        return $this->convertToCsv($processedData);
    }

    public function transform($data): string
    {
        return $this->processAndGetCsv($data, '[HARDCODED]');
    }
}
