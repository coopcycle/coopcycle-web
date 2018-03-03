<?php

namespace AppBundle\Form;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Task;
use AppBundle\Service\TagManager;
use Craue\ConfigBundle\Util\Config;
use GuzzleHttp\Client;
use League\Csv\Exception as CsvReaderException;
use League\Csv\Reader as CsvReader;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type as FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;

class TaskUploadType extends AbstractType
{
    private $config;
    private $client;
    private $translator;
    private $tagManager;

    const DATE_PATTERN_HYPHEN = '/(?<year>[0-9]{4})?-?(?<month>[0-9]{2})-(?<day>[0-9]{2})/';
    const DATE_PATTERN_SLASH = '#(?<day>[0-9]{2})/(?<month>[0-9]{2})/?(?<year>[0-9]{4})?#';
    const TIME_PATTERN = '/(?<hour>[0-9]{1,2})[:hH]+(?<minute>[0-9]{1,2})?/';

    public function __construct(Config $config, Client $client, TranslatorInterface $translator, TagManager $tagManager)
    {
        $this->config = $config;
        $this->client = $client;
        $this->translator = $translator;
        $this->tagManager = $tagManager;
    }

    private function validateHeader(array $header)
    {
        if (!in_array('address', $header)) {
            throw new \Exception($this->translator->trans('You must provide an "address" column'));
        }
    }

    private function parseDate(\DateTime $date, $text)
    {
        if (1 === preg_match(self::DATE_PATTERN_HYPHEN, $text, $matches)) {
            $date->setDate(isset($matches['year']) ? $matches['year'] : $date->format('Y'), $matches['month'], $matches['day']);
        } elseif (1 === preg_match(self::DATE_PATTERN_SLASH, $text, $matches)) {
            $date->setDate(isset($matches['year']) ? $matches['year'] : $date->format('Y'), $matches['month'], $matches['day']);
        }
    }

    private function parseTime(\DateTime $date, $text)
    {
        if (1 === preg_match(self::TIME_PATTERN, $text, $matches)) {
            $date->setTime($matches['hour'], isset($matches['minute']) ? $matches['minute'] : 00);
        }
    }

    private function applyType(Task $task, $type)
    {
        $type = strtoupper($type);

        if ($type === Task::TYPE_PICKUP) {
            $task->setType(Task::TYPE_PICKUP);
        }

        if ($type === Task::TYPE_DROPOFF) {
            $task->setType(Task::TYPE_DROPOFF);
        }
    }

    private function applyTags(TaggableInterface $task, $tagsAsString)
    {
        $tagsAsString = trim($tagsAsString);

        if (!empty($tagsAsString)) {
            $slugs = explode(' ', $tagsAsString);
            $tags = $this->tagManager->fromSlugs($slugs);
            $task->setTags($tags);
        }
    }

    private function parseTimeWindow(array $record, \DateTime $defaultDate)
    {
        // Default fallback values
        $doneAfter = clone $defaultDate;
        $doneAfter->setTime(00, 00);

        $doneBefore = clone $defaultDate;
        $doneBefore->setTime(23, 59);

        if (isset($record['after'])) {
            $this->parseDate($doneAfter, $record['after']);
            $this->parseTime($doneAfter, $record['after']);

        }

        if (isset($record['before'])) {
            $this->parseDate($doneBefore, $record['before']);
            $this->parseTime($doneBefore, $record['before']);
        }

        return [ $doneAfter, $doneBefore ];
    }

    private function geocodeAddress($taskAddress)
    {
        $apiKey = $this->config->get('google_api_key');

        $response = $this->client->request('GET', "/maps/api/geocode/json?address={$taskAddress}&key={$apiKey}");
        $data = json_decode($response->getBody(), true);

        if ($data['status'] !== 'OK') {
            $message = $this->translator->trans('Could not geocode address %address%', ['%address%' => $taskAddress]);
            throw new \Exception($message);
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
        $address->setGeo(new GeoCoordinates($location['lat'], $location['lng']));
        $address->setStreetAddress($result['formatted_address']);
        $address->setAddressLocality($addressLocality);
        $address->setPostalCode($postalCode);

        return $address;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('file', FormType\FileType::class, array(
                'mapped' => false,
                'required' => true,
                'label' => 'form.task_upload.file'
            ));

        $builder->get('file')->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {

            $file = $event->getData();

            $csv = CsvReader::createFromPath($file->getPathname(), 'r');
            $csv->setHeaderOffset(0);

            $header = $csv->getHeader();

            try {
                $this->validateHeader($header);
            } catch (\Exception $e) {
                $event->getForm()->addError(new FormError($e->getMessage()));
                return;
            }

            try {

                foreach ($csv as $record) {

                    [ $doneAfter, $doneBefore ] = $this->parseTimeWindow($record, $options['date']);

                    try {
                        $address = $this->geocodeAddress($record['address']);
                    } catch (\Exception $e) {
                        $event->getForm()->addError(new FormError($e->getMessage()));
                        return;
                    }

                    if (isset($record['address.name']) && !empty($record['address.name'])) {
                        $address->setName($record['address.name']);
                    }

                    $task = new Task();
                    $task->setAddress($address);
                    $task->setDoneAfter($doneAfter);
                    $task->setDoneBefore($doneBefore);

                    if (isset($record['type'])) {
                        $this->applyType($task, $record['type']);
                    }

                    if (isset($record['tags'])) {
                        $this->applyTags($task, $record['tags']);
                    }

                    if (isset($record['comments']) && !empty($record['comments'])) {
                        $task->setComments($record['comments']);
                    }

                    $tasks[] = $task;
                }

            } catch (CsvReaderException $e) {
                $message = $this->translator->trans('The CSV file is not valid');
                $event->getForm()->addError(new FormError($message));
                return;
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
