<?php

namespace AppBundle\Spreadsheet;

/**
 * A class to keep the relation between a row from a file
 * and the entity that is created or the errors that occurs
 * when the import and parse of that fail happens.
 */
class SpreadsheetParseResult
{
    /**
     * [
     *  1 => SomeEntity,
     *  2 => SomeEntity,
     *  5 => SomeEntity
     * ]
     */
    private $data;

    /**
     * [
     *  3 => ["error 1", "error 2"],
     *  4 => ["error 1"]
     * ]
     */
    private $errors;

    public function __construct()
    {
        $this->data = [];
        $this->errors = [];
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function addData($rowNumber, $item)
    {
        $this->data[$rowNumber] = $item;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getSortedErrors()
    {
        ksort($this->errors);
        return $this->errors;
    }

    public function setErrors($errors)
    {
        $this->errors = $errors;
    }

    public function addErrorToRow($rowNumber, $error)
    {
        if (array_key_exists($rowNumber, $this->errors)) {
            array_push($this->errors[$rowNumber], $error);
        } else {
            $this->errors[$rowNumber] = [$error];
        }
    }

    public function rowHasErrors($rowNumber)
    {
        return array_key_exists($rowNumber, $this->errors);
    }

    public function hasErrors()
    {
        return count(array_keys($this->errors)) > 0;
    }

    public function getNormalizedErrors(): array
    {
        return array_map(
            fn (int $row, array $errors) => ['row' => $row, 'errors' => $errors],
            array_keys($this->errors),
            array_values($this->errors)
        );
    }
}
