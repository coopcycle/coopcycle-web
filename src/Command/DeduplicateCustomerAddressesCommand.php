<?php

namespace AppBundle\Command;

use AppBundle\Utils\GeoUtils;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

Class DeduplicateCustomerAddressesCommand extends ContainerAwareCommand
{
    private $connection;
    private $entityManager;

    public function __construct(
        Connection $connection,
        EntityManagerInterface $entityManager)
    {
        $this->connection = $connection;
        $this->entityManager = $entityManager;

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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
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
        }

        return 0;
    }
}
