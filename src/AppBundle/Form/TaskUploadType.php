<?php

namespace AppBundle\Form;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Task;
use Craue\ConfigBundle\Util\Config;
use GuzzleHttp\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type as FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskUploadType extends AbstractType
{
    private $config;
    private $client;

    public function __construct(Config $config, Client $client)
    {
        $this->config = $config;
        $this->client = $client;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('file', FormType\FileType::class, array(
                'mapped' => false,
                'required' => true,
                'label' => 'form.task_upload.file'
            ));
            ;

        $builder->get('file')->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {

            $apiKey = $this->config->get('google_api_key');

            $file = $event->getData();

            $rows = [];
            if (($handle = fopen($file->getPathname(), 'r')) !== false) {
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $rows[] = $data;
                }
                fclose($handle);
            }

            array_shift($rows);

            $tasks = [];
            foreach ($rows as $row) {

                $taskName = $row[0];
                $taskAddress = $row[1];
                $taskTime = $row[2];

                $response = $this->client->request('GET', "/maps/api/geocode/json?address={$taskAddress}&key={$apiKey}");
                $data = json_decode($response->getBody(), true);

                if ($data['status'] !== 'OK') {
                    $message = sprintf('Could not geocode address %s', $taskAddress);
                    $event->getForm()->addError(new FormError($message));
                    break;
                }

                $result = $data['results'][0];

                $postalCode = null;
                $addressLocality = null;
                foreach ($result['address_components'] as $addressComponent) {
                    if (in_array('postal_code', $addressComponent['types'])) {
                        $postalCode = $addressComponent['long_name'];
                    }
                    if (in_array('locality', $addressComponent['types'])) {
                        $addressLocality = $addressComponent['long_name'];
                    }
                }

                $location = $result['geometry']['location'];

                $address = new Address();
                $address->setName($taskName);
                $address->setGeo(new GeoCoordinates($location['lat'], $location['lng']));
                $address->setStreetAddress($result['formatted_address']);
                $address->setAddressLocality($addressLocality);
                $address->setPostalCode($postalCode);

                [ $after, $before ] = explode('-', $taskTime);
                [ $afterHour, $afterMinute ] = explode(':', $after);
                [ $beforeHour, $beforeMinute ] = explode(':', $before);

                $doneAfter = clone $options['date'];
                $doneAfter->setTime($afterHour, $afterMinute);

                $doneBefore = clone $options['date'];
                $doneBefore->setTime($beforeHour, $beforeMinute);

                $task = new Task();
                $task->setAddress($address);
                $task->setDoneAfter($doneAfter);
                $task->setDoneBefore($doneBefore);

                $tasks[] = $task;
            }

            $taskImport = $event->getForm()->getParent()->getData();
            $taskImport->tasks = $tasks;

            $event->getForm()->getParent()->setData($taskImport);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => \stdClass::class,
            'date' => new \DateTime()
        ));
    }
}
