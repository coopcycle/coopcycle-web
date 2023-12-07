<?php

namespace AppBundle\Command;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Service\Geocoder;
use AppBundle\Service\SettingsManager;
use DBShenker\DBShenkerOptions;
use DBShenker\DBShenkerSync;
use DBShenker\DBShenker;
use DBShenker\DTO\CommunicationMean;
use DBShenker\DTO\GR7;
use DBShenker\DTO\Mesurement;
use DBShenker\DTO\NameAndAddress;
use DBShenker\Enum\CommunicationMeanType;
use DBShenker\Enum\NameAndAddressType;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Adapter\Ftp;
use League\Flysystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;

class SyncTransportersCommand extends Command {

    private readonly Address $HQAdress;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $params,
        private SettingsManager $settingsManager,
        private Geocoder $geocoder,
        private PhoneNumberUtil $phoneUtil,
    )
    {
        parent::__construct();
        [$lat, $lng] = explode(',', $this->settingsManager->get('latlng'));
        $this->HQAdress = $this->geocoder->reverse(floatval($lat), floatval($lng));
    }

    protected function configure(): void
    {
        $this->setName('coopcycle:transporters:sync')
        ->setDescription('Synchronizes transporters with the API');
    }


    protected function execute($input, $output): int
    {
        $repo = $this->entityManager->getRepository(Store::class);
        $store = $repo->findOneBy(['DBShenkerEnabled' => true]);
        if (is_null($store)) {
            throw new \Exception('No store with transporter connected');
        }

        $auth_details = parse_url($this->params->get('dbshenker_sync_uri'));

        $filesystem = new Filesystem(new Ftp([
            'host' => $auth_details['host'],
            'username' => $auth_details['user'],
            'password' => $auth_details['pass'],
            'port' => $auth_details['port'] ?? 21,
            'root' => $auth_details['path'] ?? '',
            'ssl' => false
        ]));

        $db_opts = new DBShenkerOptions(
            "CoopX", "SIRET_COOP",
            "DBShenkerTransporter", "SIRET_TRANSPORTER",
            $filesystem, "coopx"
        );

        $sync = new DBShenkerSync($db_opts);

        // Each sync can contain multiple files
        // Each file can contain multiple messages
        // Each message can contain multiple tasks

        foreach ($sync->pull() as $content) {
            $messages = DBShenker::parse($content);
            foreach ($messages as $tasks) {
                foreach ($tasks->getTasks() as $task) {
                    $this->importTask($task, $store);
                    die("Only imported one task.");
                }
            }
        }

        return 0;
    }

    // Here we need to check if the address is conform and fillable
    // Translate DBShenker entity into CC entity
    // Save EDIFACT message
    private function importTask(GR7 $task, Store $store): void {
        print_r("Task ID: ".$task->getID()."\n");
        print_r("Recipient address: ".$task->getNamesAndAddresses(NameAndAddressType::RECIPIENT)[0]->getAddress()."\n");
        print_r("Number of packages: ".count($task->getPackages())."\n");
        print_r("Total weight: ".array_sum(array_map(fn(Mesurement $p) => $p->getQuantity() ,$task->getMesurements()))." kg\n");
        print_r("Comments: ".$task->getComments()."\n");
        print_r("\n\n");



        // PICKUP SETUP
        $pickup = $this->generatePickupTask();

        // DROPOFF SETUP
        $nad = $task->getNamesAndAddresses(NameAndAddressType::RECIPIENT)[0];
        $address = $this->DBShenkerToCCAddress($nad);

        $CCTask = new Task();
        $CCTask->setAddress($address);
        $CCTask->setComments($task->getComments());

        //TODO: Check if mesurements are in kg
        $weight = array_sum(array_map(
            fn(Mesurement $p) => $p->getQuantity(),
            $task->getMesurements()
        ));
        $CCTask->setWeight($weight);

        // DELIVERY SETUP
        $delivery = new Delivery();
        $delivery->setTasks([$pickup, $CCTask]);
        $delivery->setStore($store);
        $delivery->setPickupRange(new DateTime(), new DateTime());
        $delivery->setDropoffRange(new DateTime(), new DateTime());

        $this->entityManager->persist($delivery);
        $this->entityManager->flush();

    }

    private function generatePickupTask(): Task
    {
        $task = new Task();
        $task->setType(Task::TYPE_PICKUP);
        $task->setAddress($this->HQAdress->clone());
        return $task;
    }

    private function DBShenkerToCCAddress(NameAndAddress $nad): Address
    {
        $address = $this->geocoder->geocode($nad->getAddress());

        if (is_null($address)) {
            $address = new Address();
        }

        $address->setCompany($nad->getAddressLabel());
        $address->setContactName($nad->getContactName());
        $address->setTelephone($this->DBShenkerToCCPhone($nad->getCommunicationMeans()));

        return $address;
    }

    /**
     * @param array<CommunicationMean> $communicationMeans
     */
    private function DBShenkerToCCPhone(array $communicationMeans): ?PhoneNumber
    {
        $phone = collect($communicationMeans)
        ->filter(fn(CommunicationMean $c) => $c->getType() === CommunicationMeanType::PHONE)
        ->map(fn(CommunicationMean $c) => $c->getValue())
        ->first();

        if (!is_null($phone)) {
            try {
                //TODO: Handle country code
                $phone = $this->phoneUtil->parse($phone, 'FR');
            } catch (\Exception $e) {
                return null;
            }
        }

        return $phone;

    }
}
