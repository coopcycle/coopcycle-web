<?php

namespace AppBundle\Transporter;

use Transporter\Enum\TransporterName;
use Transporter\Transporters\DBSchenker\DBSchenkerSync;
use Transporter\Transporters\DBSchenker\Generator\DBSchenkerInterchange;
use Transporter\Transporters\DBSchenker\Generator\DBSchenkerReportGenerator;

/**
* Class TransporterImpl
* @method string getSync()
* @method string getReportGenerator()
* @method string getInterchange()
*/
class TransporterImpl {

    private array $implementations = [
        'DBSCHENKER' => [
            'sync' => DBSchenkerSync::class,
            'reportGenerator' => DBSchenkerReportGenerator::class,
            'interchange' => DBSchenkerInterchange::class
        ],
        'BMV' => [

        ]
    ];

    //TODO: Move this class inside the transporter lib.
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
