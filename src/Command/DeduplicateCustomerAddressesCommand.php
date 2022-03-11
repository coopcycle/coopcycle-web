<?php

namespace AppBundle\Command;

use AppBundle\Utils\GeoUtils;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

Class DeduplicateCustomerAddressesCommand extends Command
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:data:deduplicate-addresses')
            ->setDescription('Deduplicates customer addresses.')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Dry run'
            )
            ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dryRun = $input->getOption('dry-run');

        $getCustomerEmail = $this->connection
            ->prepare("SELECT email_canonical FROM sylius_customer WHERE id = :id");

        $getCustomerAddresses = $this->connection
            ->prepare("SELECT a.id, a.street_address, ST_AsText(a.geo) AS coords, a.name, a.description, a.telephone, a.contact_name, a.postal_code, a.address_locality FROM address a JOIN sylius_customer_address ca ON a.id = ca.address_id WHERE ca.customer_id = :customer_id");

        $stmt = $this->connection
            ->prepare("SELECT customer_id, COUNT(*) AS address_count FROM sylius_customer_address GROUP BY customer_id HAVING COUNT(*) > 1 ORDER BY COUNT(*) DESC");
        $stmt->execute();

        while ($customer = $stmt->fetch()) {

            $getCustomerEmail->bindParam('id', $customer['customer_id']);
            $getCustomerEmail->execute();

            $email = $getCustomerEmail->fetchColumn();

            $this->io->text(sprintf('Customer "%s" has %d addresses', $email, $customer['address_count']));

            $getCustomerAddresses->bindParam('customer_id', $customer['customer_id']);
            $getCustomerAddresses->execute();

            $customerAddresses = [];
            while ($address = $getCustomerAddresses->fetch()) {
                $customerAddresses[$address['coords']][] = $address;
                // $coords = GeoUtils::asGeoCoordinates($address['coords']);
            }

            $duplicates = [];
            foreach ($customerAddresses as $coords => $addresses) {
                if (count($addresses) > 1) {
                    $duplicates[$coords] = $addresses;
                }
            }

            if (count($duplicates) === 0) {
                $this->io->text(sprintf('No duplicates found for customer "%s"', $email));
                continue;
            }

            $tableData = [];
            $sortedDuplicates = [];
            foreach ($duplicates as $coords => $addresses) {

                usort($addresses, function ($a, $b) {

                    // Both have description
                    if (!empty($a['description']) && !empty($b['description'])) {

                        // Both have telephone
                        if (!empty($a['telephone']) && !empty($b['telephone'])) {

                            // Both have contact name
                            if (!empty($a['contact_name']) && !empty($b['contact_name'])) {
                                return $a['id'] > $b['id'] ? -1 : 1;
                            }

                            if (!empty($a['contact_name']) && empty($b['contact_name'])) {
                                return -1;
                            }

                            if (empty($a['contact_name']) && !empty($b['contact_name'])) {
                                return 1;
                            }

                            return $a['id'] > $b['id'] ? -1 : 1;
                        }

                        if (!empty($a['telephone']) && empty($b['telephone'])) {
                            return -1;
                        }

                        if (empty($a['telephone']) && !empty($b['telephone'])) {
                            return 1;
                        }

                        return $a['id'] > $b['id'] ? -1 : 1;
                    }

                    if (!empty($a['description']) && empty($b['description'])) {
                        return -1;
                    }

                    if (empty($a['description']) && !empty($b['description'])) {
                        return -1;
                    }

                    return $a['id'] > $b['id'] ? -1 : 1;
                });

                $sortedDuplicates[$coords] = $addresses;
            }

            foreach ($sortedDuplicates as $coords => $addresses) {
                foreach ($addresses as $addr) {
                    $tableData[] = [
                        $email,
                        $addr['id'],
                        $addr['street_address'],
                        $addr['telephone'],
                        $addr['contact_name']
                    ];
                }
            }

            $this->io->table(
                ['Email', 'ID', 'Street address', 'Telephone', 'Contact name'],
                $tableData
            );

            // https://www.doctrine-project.org/projects/doctrine-orm/en/2.7/reference/transactions-and-concurrency.html
            if (!$dryRun) {
                $this->io->text('Starting transaction…');
                $this->connection->beginTransaction(); // suspend auto-commit
            }

            try {

                foreach ($sortedDuplicates as $coords => $addresses) {

                    $bestAddress = array_shift($addresses);
                    $otherAddressesIds = array_map(fn($address) => $address['id'], $addresses);

                    $updateTaskAddressSQL =
                        sprintf('UPDATE task SET address_id = :best_address_id WHERE address_id IN (:other_addresses_ids)');
                    $updateOrderAddressSQL =
                        sprintf('UPDATE sylius_order SET shipping_address_id = :best_address_id WHERE shipping_address_id IN (:other_addresses_ids)');
                    $deleteCustomerAddressesSQL =
                        sprintf('DELETE FROM sylius_customer_address WHERE customer_id = :customer_id AND address_id IN (:other_addresses_ids)');
                    $deleteAddressesSQL =
                        sprintf('DELETE FROM address WHERE id IN (:other_addresses_ids)');

                    $this->debugStatement($updateTaskAddressSQL, [
                        'best_address_id' => $bestAddress['id'],
                        'other_addresses_ids' => $otherAddressesIds,
                    ]);
                    $this->debugStatement($updateOrderAddressSQL, [
                        'best_address_id' => $bestAddress['id'],
                        'other_addresses_ids' => $otherAddressesIds,
                    ]);

                    $this->debugStatement($deleteCustomerAddressesSQL, [
                        'customer_id' => $customer['customer_id'],
                        'other_addresses_ids' => $otherAddressesIds,
                    ]);
                    $this->debugStatement($deleteAddressesSQL, [
                        'other_addresses_ids' => $otherAddressesIds,
                    ]);

                    if (!$dryRun) {
                        $this->connection->executeQuery(
                            $updateTaskAddressSQL,
                            [ 'best_address_id' => $bestAddress['id'],     'other_addresses_ids' => $otherAddressesIds ],
                            [ 'best_address_id' => ParameterType::INTEGER, 'other_addresses_ids' => Connection::PARAM_INT_ARRAY ]
                        );
                        $this->connection->executeQuery(
                            $updateOrderAddressSQL,
                            [ 'best_address_id' => $bestAddress['id'],     'other_addresses_ids' => $otherAddressesIds ],
                            [ 'best_address_id' => ParameterType::INTEGER, 'other_addresses_ids' => Connection::PARAM_INT_ARRAY ]
                        );

                        $this->connection->executeQuery(
                            $deleteCustomerAddressesSQL,
                            [ 'customer_id' => $customer['customer_id'], 'other_addresses_ids' => $otherAddressesIds ],
                            [ 'customer_id' => ParameterType::INTEGER,   'other_addresses_ids' => Connection::PARAM_INT_ARRAY ]
                        );
                        $this->connection->executeQuery(
                            $deleteAddressesSQL,
                            [ 'other_addresses_ids' => $otherAddressesIds ],
                            [ 'other_addresses_ids' => Connection::PARAM_INT_ARRAY ]
                        );
                    }
                }

                if (!$dryRun) {
                    $this->io->text('Commit transaction…');
                    $this->connection->commit();
                }

            } catch (\Exception $e) {
                $this->connection->rollBack();
                throw $e;
            }
        }

        return 0;
    }

    private function debugStatement($sql, $params)
    {
        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = sprintf('%s = %s', $key, (is_array($value) ? implode(',', $value) : $value));
        }

        $this->io->text(sprintf('%s with params %s', $sql, implode(', ', $parts)));
    }
}
