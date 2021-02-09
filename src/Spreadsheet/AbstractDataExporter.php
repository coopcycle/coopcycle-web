<?php

namespace AppBundle\Spreadsheet;

use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Writer as CsvWriter;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

abstract class AbstractDataExporter
{
    protected $entityManager;
    protected $normalizer;

    public function __construct(EntityManagerInterface $entityManager, NormalizerInterface $normalizer)
    {
        $this->entityManager = $entityManager;
        $this->normalizer = $normalizer;
    }

    public function export(\DateTime $start, \DateTime $end)
    {
        $data = $this->getData($start, $end);

        $records = $this->normalizer->normalize($data, 'csv');

        if (count($records) === 0) {
            throw new \Exception('Empty export');
        }

        $csv = CsvWriter::createFromString('');
        $csv->insertOne(array_keys($records[0]));

        $csv->insertAll($records);

        return $csv->getContent();
    }

    abstract protected function getData(\DateTime $start, \DateTime $end): array;
}
