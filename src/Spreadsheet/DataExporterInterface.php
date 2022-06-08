<?php

namespace AppBundle\Spreadsheet;

interface DataExporterInterface
{
    public function export(\DateTime $start, \DateTime $end): string;
    public function getContentType(): string;
    public function getFilename(\DateTime $start, \DateTime $end): string;
}
