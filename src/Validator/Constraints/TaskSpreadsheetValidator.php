<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Spreadsheet\TaskSpreadsheetParser;
use Oneup\UploaderBundle\Uploader\File\FlysystemFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class TaskSpreadsheetValidator extends ConstraintValidator
{
    use SpreadsheetReadabilityTrait;

    public function __construct(private TaskSpreadsheetParser $parser)
    {}

    public function validate($value, Constraint $constraint)
    {
        if (null === $value) {
            return;
        }

        // The columns will be matched to the fields they hold, so what the header says
        // is beside the point: the file only has to be readable
        if ($constraint->mapped) {
            $this->validateReadability($value, $constraint, $this->parser);

            return;
        }

        // The dispatch board's uploader and the API read the file by its header names
        if (!$value instanceof FlysystemFile) {
            throw new UnexpectedValueException($value, FlysystemFile::class);
        }

        $fileSystem = $value->getFilesystem();
        $mimeType = $value->getMimeType();

        // For CSV files, we need to make sure they are in UTF-8
        if (in_array($mimeType, ['text/csv', 'text/plain'])) {

            // Make sure the file is in UTF-8
            $encoding = mb_detect_encoding($fileSystem->read($value->getPathname()), ['UTF-8', 'Windows-1252'], true);

            if ($encoding !== 'UTF-8') {
                $this->context->buildViolation($constraint->csvEncodingMessage)
                    ->addViolation();
                return;
            }
        }

        $spreadsheet = $this->parser->loadSpreadsheet($value);

        $header = $this->parser->getHeaderRow($spreadsheet);

        $hasAddress = in_array('address', $header);
        $hasStreetAddress = in_array('address.streetAddress', $header);
        $hasLatLong = in_array('latlong', $header);
        $hasAddressLatLng = in_array('address.latlng', $header);

        if (!$hasAddress && !$hasLatLong && !$hasStreetAddress && !$hasAddressLatLng) {
            $this->context->buildViolation($constraint->missingColumnsMessage)
                ->setParameter('%column%', 'address')
                ->setParameter('%other_column%', 'latlong')
                ->addViolation();
        }

        if ($hasAddress && $hasStreetAddress) {
            $this->context->buildViolation($constraint->alternativeColumnsMessage)
                ->setParameter('%column%', 'address')
                ->setParameter('%other_column%', 'address.streetAddress')
                ->addViolation();
        }

        if ($hasLatLong && $hasAddressLatLng) {
            $this->context->buildViolation($constraint->alternativeColumnsMessage)
                ->setParameter('%column%', 'latlong')
                ->setParameter('%other_column%', 'address.latlng')
                ->addViolation();
        }
    }
}

