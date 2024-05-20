<?php

namespace AppBundle\Transporter;

use Transporter\Enum\TransporterName;
use Transporter\Transporters\DBSchenker\DBSchenkerSync;
use Transporter\Transporters\DBSchenker\Generator\DBSchenkerInterchange;
use Transporter\Transporters\DBSchenker\Generator\DBSchenkerReport;

/**
* Class TransporterImpl
* @method string getSync()
* @method string getReport()
* @method string getInterchange()
*/
class TransporterImpl {

    private array $implementations = [
        TransporterName::DB_SCHENKER->value => [
            'sync' => DBSchenkerSync::class,
            'report' => DBSchenkerReport::class,
            'interchange' => DBSchenkerInterchange::class
        ],
        TransporterName::BMV->value => [

        ]
    ];

    public function __construct(
        private string $implementation
    )
    { }

    public function __get(string $name): string
    {
        if (isset($this->implementations[$this->implementation][$name])) {
            return $this->implementations[$this->implementation][$name];
        }
        throw new TransporterException(
            sprintf("Transporter %s does not support %s",
                $this->implementation, $name)
        );
    }



}
