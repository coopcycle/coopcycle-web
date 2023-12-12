<?php

namespace AppBundle\Command;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Store;
use AppBundle\Entity\Tag;
use AppBundle\Entity\Task;
use AppBundle\Entity\TimeSlot;
use AppBundle\Service\Geocoder;
use AppBundle\Service\SettingsManager;
use Carbon\Carbon;
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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;

class SyncTransportersCommand extends Command {

    private readonly Address $HQAdress;
    private readonly Store $store;
    private readonly GeoCoordinates $defaultCoordinates;

    private bool $dryRun;
    private OutputInterface $output;

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
        $this->defaultCoordinates = new GeoCoordinates($lat, $lng);
        $repo = $this->entityManager->getRepository(Store::class);

        /** @var Store $store */
        $store = $repo->findOneBy(['DBShenkerEnabled' => true]);
        if (is_null($store)) {
            throw new \Exception('No store with transporter connected');
        }

        $address = $store->getAddress();
        if (is_null($address)) {
            throw new \Exception('Store without address');
        }

        $this->store = $store;
        $this->HQAdress = $address;

    }

    protected function configure(): void
    {
        $this->setName('coopcycle:transporters:sync')
        ->setDescription('Synchronizes transporters with the API');

        $this->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Dry run mode');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->dryRun = $input->getOption('dry-run');
        $this->output = $output;

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

        if ($this->dryRun) {
            $output->writeln("Dry run mode, nothing will be imported");
        }
        $output->writeln("Start syncing...");

        $count = 0;
        foreach ($sync->pull() as $content) {
            $edi = $this->storeEDI($content);
            $messages = DBShenker::parse($content);
            foreach ($messages as $tasks) {
                foreach ($tasks->getTasks() as $task) {
                    $this->importTask($task);
                    $count++;
                }
            }
        }
        $output->writeln("Done syncing, imported $count tasks");

        return 0;
    }

    // Here we need to check if the address is conform and fillable
    // Translate DBShenker entity into CC entity
    // Save EDIFACT message
    private function importTask(GR7 $task): void {
        if ($this->output->isVerbose()) {
            $this->output->writeln("Task ID: ".$task->getId()."\n");
            $this->output->writeln("Recipient address: ".$task->getNamesAndAddresses(NameAndAddressType::RECIPIENT)[0]->getAddress());
            $this->output->write("Times: ");
            $this->output->writeln(collect($task->getDates())->map(fn($date) => $date->getEvent()->name . ' -> ' . $date->getDate()->format('d/m/Y'))->join("\n"));
            $this->output->writeln("Number of packages: ".count($task->getPackages()));
            $this->output->writeln("Total weight: ".array_sum(array_map(fn(Mesurement $p) => $p->getQuantity() ,$task->getMesurements()))." kg");
            $this->output->writeln("Comments: ".$task->getComments());
            $this->output->writeln("");
        }


        // PICKUP SETUP
        $pickup = $this->generatePickupTask();

        // DROPOFF SETUP
        $nad = $task->getNamesAndAddresses(NameAndAddressType::RECIPIENT);
        if (count($nad) !== 1) {
            throw new \Exception("Cannot handle multiple recipients");
        }
        $nad = $nad[0];
        $address = $this->DBShenkerToCCAddress($nad);

        $imported_from = sprintf(
            "%s\n%s\n\n%s\n",
            $nad->getAddressLabel(),
            $nad->getAddress(),
            $nad->getContactName()
        );
        $imported_from .= collect($nad->getCommunicationMeans())
        ->map(fn(CommunicationMean $c) => $c->getType()->name . ': ' . $c->getValue())
        ->join("\n");

        $CCTask = new Task();
        $CCTask->setPrevious($pickup);
        $CCTask->setAddress($address);
        $CCTask->setComments($task->getComments());
        $CCTask->setMetadata('imported_from', $imported_from);

        if ($address->getGeo()->isEqualTo($this->defaultCoordinates)) {
            $this->output->writeln("Address without coordinates: ".$nad->getAddress());
            $CCTask->setTags('review-needed');
        }

        //TODO: Check if mesurements are in kg
        $weight = array_sum(array_map(
            fn(Mesurement $p) => $p->getQuantity(),
            $task->getMesurements()
        ));
        $CCTask->setWeight($weight * 1000);

        //TODO: Check if product is premium only based on its name or not
        if (str_contains($task->getProductClass()->name, 'PREMIUM')) {
            $CCTask->setTags('premium');
        }


        // DELIVERY SETUP
        $delivery = new Delivery();
        $delivery->setTasks([$pickup, $CCTask]);
        $delivery->setStore($this->store);
        $delivery->setPickupRange($this->startOfDay(), $this->endOfDay());
        $delivery->setDropoffRange($this->startOfDay(), $this->endOfDay());

        if (!$this->dryRun) {
            $this->entityManager->persist($pickup);
            $this->entityManager->persist($CCTask);
            $this->entityManager->persist($delivery);
            $this->entityManager->flush();
        }
    }

    private function startOfDay(): \DateTime {
        $carbon = new Carbon();
        return $carbon->startOfDay()->toDateTime();
    }

    private function endOfDay(): \DateTime {
        $carbon = new Carbon();
        return $carbon->endOfDay()->toDateTime();
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
            $address->setGeo($this->defaultCoordinates);
            $address->setStreetAddress('INVALID ADDRESS');
        }


        $address->setCompany($nad->getAddressLabel());
        $address->setName($nad->getAddressLabel());
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

    private function storeEDI(string $content): EDIFACTMessage
    {
        $edi = new EDIFACTMessage();
        return $edi;

    }
}
